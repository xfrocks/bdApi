<?php

namespace Xfrocks\Api\Repository;

use GuzzleHttp\Exception\ClientException;
use XF\Mvc\Entity\Repository;
use XF\Util\Php;

class PingQueue extends Repository
{
    public function insertQueue($callback, $objectType, array $data, $expireDate = 0, $queueDate = 0)
    {
        $this->db()->insert('xf_bdapi_ping_queue', array(
            'callback_md5' => md5($callback),
            'callback' => $callback,
            'object_type' => $objectType,
            'data' => serialize($data),
            'queue_date' => $queueDate,
            'expire_date' => $expireDate,
        ));

        if ($this->options()->bdApi_pingQueueUseDefer) {
            $triggerDate = null;
            if ($queueDate > 0) {
                $triggerDate = $queueDate;
            }

            $this->app()
                ->jobManager()
                ->enqueueLater(__CLASS__, $triggerDate, 'Xfrocks\Api\Job\PingQueue', [], false);
        }
    }

    public function reInsertQueue($records)
    {
        foreach ($records as $record) {
            $data = $record['data'];

            if (!isset($data['_retries'])) {
                $data['_retries'] = 0;
            } else {
                $data['_retries']++;
            }
            if ($data['_retries'] > 5) {
                // too many tries
                continue;
            }

            $queueDate = time() + 60 * pow(2, $data['_retries'] - 1);

            $this->insertQueue($record['callback'], $record['object_type'], $data, $record['expire_date'], $queueDate);
        }
    }

    public function hasQueue()
    {
        $minId = $this->db()->fetchOne('
            SELECT MIN(ping_queue_id)
			FROM xf_bdapi_ping_queue
			WHERE queue_date < ?
        ', \XF::$time);

        return (bool) $minId;
    }

    public function getQueue($limit = 20)
    {
        $queueRecords = $this->db()->fetchAllKeyed($this->db()->limit('
            SELECT *
			FROM xf_bdapi_ping_queue
			WHERE queue_date < ?
			ORDER BY callback_md5
        ', $limit), 'ping_queue_id', [\XF::$time]);

        foreach ($queueRecords as &$record) {
            $record['data'] = Php::safeUnserialize($record['data']);
        }

        return $queueRecords;
    }

    public function run($maxRunTime)
    {
        $s = microtime(true);

        do {
            $queueRecords = $this->getQueue($maxRunTime ? 20 : 0);

            $this->ping($queueRecords);

            if ($maxRunTime && microtime(true) - $s > $maxRunTime) {
                break;
            }
        } while($queueRecords);

        return $this->hasQueue();
    }

    public function ping(array $queueRecords)
    {
        while (count($queueRecords) > 0) {
            $records = [];

            foreach (array_keys($queueRecords) as $key) {
                if (count($records) == 0
                    || $queueRecords[$key]['callback'] === $records[0]['callback']
                ) {
                    $record = $queueRecords[$key];
                    unset($queueRecords[$key]);

                    if (!$this->db()->delete(
                        'xf_bdapi_ping_queue',
                        'ping_queue_id = ' . intval($record['ping_queue_id'])
                    )
                    ) {
                        // already been deleted - run elsewhere
                        continue;
                    }

                    if ($record['expire_date'] > 0 AND $record['expire_date'] < \XF::$time) {
                        // expired
                        continue;
                    }

                    $records[] = $record;
                }
            }

            $payloads = $this->preparePayloadsFromRecords($records);
            if (empty($payloads)) {
                continue;
            }

            $client = $this->app()->http()->client();
            $reInserted = false;

            try {
                $response = $client->post($records[0]['callback'], [
                    'json' => $payloads
                ]);

                $responseBody = $response->getBody()->getContents();
                $responseCode = $response->getStatusCode();
            } catch (ClientException $e) {
                $response = null;
                $responseBody = $e->getMessage();
                $responseCode = 500;
            }

            if ($responseCode < 200 OR $responseCode > 299) {
                $this->reInsertQueue($records);
                $reInserted = true;
            }

            if (\XF::$debugMode || $reInserted) {
                /** @var Log $logRepo */
                $logRepo = $this->repository('Xfrocks\Api:Log');
                $logRepo->logRequest(
                    'POST',
                    $records[0]['callback'],
                    $payloads,
                    $responseCode,
                    array('message' => $responseBody),
                    array(
                        'client_id' => '',
                        'user_id' => 0,
                        'ip_address' => '127.0.0.1',
                    )
                );
            }
        }
    }

    protected function preparePayloadsFromRecords(array $records)
    {
        $dataByTypes = array();
        $payloads = array();

        foreach ($records as $key => $record) {
            if (!isset($dataByTypes[$record['object_type']])) {
                $dataByTypes[$record['object_type']] = array();
            }
            $dataByTypes[$record['object_type']][$key] = $record['data'];
        }

        /** @var Subscription $subscriptionRepo */
        $subscriptionRepo = $this->repository('Xfrocks\Api:Subscription');
        foreach ($dataByTypes as $objectType => &$dataMany) {
            $dataMany = $subscriptionRepo->preparePingDataMany($objectType, $dataMany);
        }

        foreach ($records as $key => $record) {
            if (!empty($dataByTypes[$record['object_type']][$key])) {
                $payloads[$key] = $dataByTypes[$record['object_type']][$key];
            }
        }

        return $payloads;
    }
}
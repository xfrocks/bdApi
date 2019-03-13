<?php

namespace Xfrocks\Api\Repository;

use GuzzleHttp\Exception\ClientException;
use XF\Mvc\Entity\Repository;
use XF\Util\Php;

class PingQueue extends Repository
{
    /**
     * @param string $callback
     * @param string $objectType
     * @param array $data
     * @param int $expireDate
     * @param int $queueDate
     * @return void
     */
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

        $triggerDate = null;
        if ($queueDate > 0) {
            $triggerDate = $queueDate;
        }

        $this->app()
            ->jobManager()
            ->enqueueLater(__CLASS__, $triggerDate, 'Xfrocks\Api\Job\PingQueue', [], false);
    }

    /**
     * @param array $records
     * @return void
     */
    public function reInsertQueue(array $records)
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

            $queueDate = time() + intval(60 * pow(2, $data['_retries'] - 1));

            $this->insertQueue($record['callback'], $record['object_type'], $data, $record['expire_date'], $queueDate);
        }
    }

    /**
     * @return bool
     */
    public function hasQueue()
    {
        $minId = $this->db()->fetchOne('
            SELECT MIN(ping_queue_id)
			FROM xf_bdapi_ping_queue
			WHERE queue_date < ?
        ', \XF::$time);

        return (bool)$minId;
    }

    /**
     * @param int $limit
     * @return array
     */
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

    /**
     * @param int $maxRunTime
     * @return bool
     */
    public function run($maxRunTime)
    {
        $s = microtime(true);

        do {
            $queueRecords = $this->getQueue($maxRunTime > 0 ? 20 : 0);

            $this->ping($queueRecords);

            if ($maxRunTime > 0 && microtime(true) - $s > $maxRunTime) {
                break;
            }
        } while ($queueRecords);

        return $this->hasQueue();
    }

    /**
     * @param array $queueRecords
     * @return void
     */
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

                    if ($record['expire_date'] > 0 and $record['expire_date'] < \XF::$time) {
                        // expired
                        continue;
                    }

                    $records[] = $record;
                }
            }

            $payloads = $this->preparePayloadsFromRecords($records);
            if (count($payloads) === 0) {
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

            if ($responseCode < 200 or $responseCode > 299) {
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

    /**
     * @param array $records
     * @return array
     */
    protected function preparePayloadsFromRecords(array $records)
    {
        $dataByTypes = array();
        $payloads = array();

        foreach ($records as $key => $record) {
            /** @var string $objectTypeRef */
            $objectTypeRef =& $record['object_type'];

            if (!isset($dataByTypes[$objectTypeRef])) {
                $dataByTypes[$objectTypeRef] = array();
            }
            $dataRef =& $dataByTypes[$objectTypeRef];

            $dataRef[$key] = $record['data'];
        }

        /** @var Subscription $subscriptionRepo */
        $subscriptionRepo = $this->repository('Xfrocks\Api:Subscription');
        foreach ($dataByTypes as $objectType => &$dataManyRef) {
            $dataManyRef = $subscriptionRepo->preparePingDataMany($objectType, $dataManyRef);
        }

        foreach ($records as $key => $record) {
            $objectTypeRef =& $record['object_type'];

            if (!isset($dataByTypes[$objectTypeRef])) {
                continue;
            }
            $dataRef =& $dataByTypes[$objectTypeRef];

            if (!isset($dataRef[$key])) {
                continue;
            }
            $payloads[$key] = $dataRef[$key];
        }

        return $payloads;
    }
}

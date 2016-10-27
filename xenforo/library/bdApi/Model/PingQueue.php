<?php

class bdApi_Model_PingQueue extends XenForo_Model
{
    public function insertQueue($callback, $objectType, array $data, $expireDate = 0, $queueDate = 0)
    {
        $this->_getDb()->insert('xf_bdapi_ping_queue', array(
            'callback_md5' => md5($callback),
            'callback' => $callback,
            'object_type' => $objectType,
            'data' => serialize($data),
            'queue_date' => $queueDate,
            'expire_date' => $expireDate,
        ));

        if (bdApi_Option::getConfig('pingQueueUseDefer')
            && is_callable(array(
                'XenForo_Application',
                'defer'
            ))
        ) {
            $triggerDate = null;
            if ($queueDate > 0) {
                $triggerDate = $queueDate;
            }

            XenForo_Application::defer('bdApi_Deferred_PingQueue', array(), __CLASS__, false, $triggerDate);
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
        $minId = $this->_getDb()->fetchOne('
			SELECT MIN(ping_queue_id)
			FROM xf_bdapi_ping_queue
			WHERE queue_date < ?
		', array(XenForo_Application::$time));

        return (bool)$minId;
    }

    public function getQueue($limit = 20)
    {
        $queueRecords = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT *
			FROM xf_bdapi_ping_queue
			WHERE queue_date < ?
			ORDER BY callback_md5
		', $limit), 'ping_queue_id', array(XenForo_Application::$time));

        foreach ($queueRecords as &$record) {
            $record['data'] = unserialize($record['data']);
        }

        return $queueRecords;
    }

    public function runQueue($targetRunTime = 0)
    {
        $s = microtime(true);

        do {
            $queueRecords = $this->getQueue($targetRunTime ? 20 : 0);

            $this->ping($queueRecords);

            if ($targetRunTime && microtime(true) - $s > $targetRunTime) {
                break;
            }
        } while ($queueRecords);

        return $this->hasQueue();
    }

    public function ping(array $queueRecords)
    {
        /* @var $logModel bdApi_Model_Log */
        $logModel = $this->getModelFromCache('bdApi_Model_Log');

        while (count($queueRecords) > 0) {
            $records = array();

            foreach (array_keys($queueRecords) as $key) {
                if (count($records) == 0 OR $queueRecords[$key]['callback'] === $records[0]['callback']) {
                    $record = $queueRecords[$key];
                    unset($queueRecords[$key]);

                    if (!$this->_getDb()->delete('xf_bdapi_ping_queue', 'ping_queue_id = ' . intval($record['ping_queue_id']))) {
                        // already been deleted - run elsewhere
                        continue;
                    }

                    if ($record['expire_date'] > 0 AND $record['expire_date'] < XenForo_Application::$time) {
                        // expired
                        continue;
                    }

                    $records[] = $record;
                }
            }

            $payloads = $this->_preparePayloadsFromRecords($records);
            if (empty($payloads)) {
                continue;
            }

            $client = XenForo_Helper_Http::getClient($records[0]['callback']);
            $client->setHeaders('Content-Type', 'application/json');
            $client->setRawData(json_encode($payloads));

            $reInserted = false;

            try {
                $response = $client->request('POST');
                $responseBody = $response->getBody();
                $responseCode = $response->getStatus();
            } catch (Zend_Http_Client_Exception $e) {
                $response = null;
                $responseBody = $e->getMessage();
                $responseCode = 500;
            }

            if ($responseCode < 200 OR $responseCode > 299) {
                $this->reInsertQueue($records);
                $reInserted = true;
            }

            if (XenForo_Application::debugMode() || $reInserted) {
                $logModel->logRequest(
                    'POST', $records[0]['callback'], $payloads,
                    $responseCode, array('message' => $responseBody),
                    array(
                        'client_id' => '',
                        'user_id' => 0,
                        'ip_address' => '127.0.0.1',
                    )
                );
            }
        }
    }

    protected function _preparePayloadsFromRecords(array $records)
    {
        $dataByTypes = array();
        $payloads = array();

        foreach ($records as $key => $record) {
            if (!isset($dataByTypes[$record['object_type']])) {
                $dataByTypes[$record['object_type']] = array();

            }
            $dataByTypes[$record['object_type']][$key] = $record['data'];
        }

        /* @var $subscriptionModel bdApi_Model_Subscription */
        $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
        foreach ($dataByTypes as $objectType => &$dataMany) {
            $dataMany = $subscriptionModel->preparePingDataMany($objectType, $dataMany);
        }

        foreach ($records as $key => $record) {
            if (!empty($dataByTypes[$record['object_type']][$key])) {
                $payloads[$key] = $dataByTypes[$record['object_type']][$key];
            }
        }

        return $payloads;
    }

}

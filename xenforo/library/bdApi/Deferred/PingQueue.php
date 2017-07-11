<?php

class bdApi_Deferred_PingQueue extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        /* @var $queueModel bdApi_Model_PingQueue */
        $queueModel = XenForo_Model::create('bdApi_Model_PingQueue');

        $hasMore = $queueModel->runQueue($targetRunTime);
        if ($hasMore) {
            return $data;
        } else {
            return false;
        }
    }
}

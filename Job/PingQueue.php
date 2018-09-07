<?php

namespace Xfrocks\Api\Job;

use XF\Job\AbstractJob;

class PingQueue extends AbstractJob
{
    public function canTriggerByChoice()
    {
        return false;
    }

    public function canCancel()
    {
        return false;
    }

    public function run($maxRunTime)
    {
        /** @var \Xfrocks\Api\Repository\PingQueue $pingQueueRepo */
        $pingQueueRepo = \XF::repository('Xfrocks\Api:PingQueue');

        if ($pingQueueRepo->run($maxRunTime)) {
            $resume = $this->resume();
            $resume->continueDate = \XF::$time;

            return $resume;
        }

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return '';
    }
}

<?php

namespace Xfrocks\Api\Job;

use XF\Job\AbstractJob;

class PingQueue extends AbstractJob
{
    /**
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canCancel()
    {
        return false;
    }

    /**
     * @param mixed $maxRunTime
     * @return \XF\Job\JobResult
     */
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

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return '';
    }
}

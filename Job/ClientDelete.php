<?php

namespace Xfrocks\Api\Job;

use XF\Job\AbstractJob;
use XF\Timer;

class ClientDelete extends AbstractJob
{
    public function canTriggerByChoice()
    {
        return true;
    }

    public function canCancel()
    {
        return false;
    }

    public function run($maxRunTime)
    {
        if (empty($this->data['clientId'])) {
            return $this->complete();
        }
        
        $timer = new Timer($maxRunTime);
        $finder = $this->app
            ->finder('Xfrocks\Api:Subscription')
            ->where('client_id', $this->data['clientId']);

        foreach ($finder->limit(100)->fetch() as $subscription) {
            if ($timer->limitExceeded()) {
                break;
            }

            $subscription->delete();
        }

        $remainTotal = $finder->total();
        if ($remainTotal > 0) {
            return $this->resume();
        }

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return '';
    }
}

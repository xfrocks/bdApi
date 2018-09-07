<?php

namespace Xfrocks\Api\Pub\View\Subscription;

use XF\Mvc\View;

class Post extends View
{
    public function renderRaw()
    {
        if (!empty($this->params['httpResponseCode'])) {
            $this->response->httpCode($this->params['httpResponseCode']);
        }

        if (!empty($this->params['message'])) {
            return $this->params['message'];
        } else {
            return '';
        }
    }
}

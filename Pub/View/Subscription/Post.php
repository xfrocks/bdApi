<?php

namespace Xfrocks\Api\Pub\View\Subscription;

use XF\Mvc\View;

class Post extends View
{
    /**
     * @return string
     */
    public function renderRaw()
    {
        if (isset($this->params['httpResponseCode'])) {
            $this->response->httpCode($this->params['httpResponseCode']);
        }

        if (isset($this->params['message'])) {
            return $this->params['message'];
        } else {
            return '';
        }
    }
}

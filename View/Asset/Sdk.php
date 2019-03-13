<?php

namespace Xfrocks\Api\View\Asset;

class Sdk extends \XF\Mvc\View
{
    /**
     * @return string
     */
    public function renderRaw()
    {
        $this->response->contentType('application/x-javascript');
        $this->response->header('Cache-Control', 'public, max-age=31536000');

        return $this->params['sdk'];
    }
}

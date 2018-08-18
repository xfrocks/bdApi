<?php

namespace Xfrocks\Api\Pub\View\Misc;

use Xfrocks\Api\Util\Cors;

class ApiData extends \XF\Mvc\View
{
    public function renderRaw()
    {
        Cors::addHeaders($this->response);

        if (!empty($this->params['callback'])) {
            $this->response->contentType('application/x-javascript');
            return sprintf('%s(%s);', $this->params['callback'], json_encode($this->params['data']));
        } else {
            $this->response->contentType('application/json');
            return json_encode($this->params['data']);
        }
    }
}

<?php

namespace Xfrocks\Api\Util;

class Cors
{
    /**
     * @param \XF\Http\Response $response
     * @return void
     */
    public static function addHeaders($response)
    {
        $app = \XF::app();
        if (!$app->options()->bdApi_cors) {
            return;
        }

        $request = $app->request();

        $origin = $request->getServer('HTTP_ORIGIN');
        if ($origin !== false) {
            $response->header('Access-Control-Allow-Origin', $origin, true);
            $response->header('Access-Control-Allow-Credentials', 'true', true);
        } else {
            $response->header('Access-Control-Allow-Origin', '*', true);
        }

        $method = $request->getServer('HTTP_ACCESS_CONTROL_REQUEST_METHOD');
        if ($method !== false) {
            $response->header('Access-Control-Allow-Methods', $method, true);
        }

        $headers = $request->getServer('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
        if ($headers !== false) {
            $response->header('Access-Control-Allow-Headers', $headers, true);
        }
    }
}

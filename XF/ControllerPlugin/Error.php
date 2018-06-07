<?php

namespace Xfrocks\Api\XF\ControllerPlugin;

use Xfrocks\Api\Transformer;

/**
 * @method \Xfrocks\Api\Mvc\Reply\Api api(array $data)
 * @see \Xfrocks\Api\Controller\AbstractController::api()
 */
class Error extends XFCP_Error
{
    public function actionRegistrationRequired()
    {
        return $this->error(\XF::phrase('login_required'), 403);
    }

    public function actionException($exception, $showDetails = null)
    {
        if ($showDetails === null) {
            $showDetails = (\XF::$debugMode || \XF::visitor()->is_admin);
        }

        if ($showDetails) {
            $app = $this->app;
            /** @var Transformer $transformer */
            $transformer = $app->container('api.transformer');
            $data = ['exception' => $transformer->transformException($exception)];
            $reply = $this->api($data);
        } else {
            $reply = $this->error(\XF::phrase('server_error_occurred'));
        }

        $reply->setResponseCode(500);
        return $reply;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Error extends \XF\ControllerPlugin\Error
    {
        // extension hint
    }
}

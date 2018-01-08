<?php

namespace Xfrocks\Api\XF\Mvc;

use XF\Mvc\ParameterBag;
use XF\Mvc\Renderer\Json;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Redirect;
use Xfrocks\Api\Mvc\Reply;

class Dispatcher extends XFCP_Dispatcher
{
    public function dispatchClass(
        $controllerClass,
        $action,
        $responseType,
        ParameterBag $params = null,
        $sectionContext = null,
        &$controller = null,
        AbstractReply $previousReply = null
    ) {
        if ($controllerClass !== 'Xfrocks:Error') {
            $method = strtolower($this->request->getServer('REQUEST_METHOD'));
            switch ($method) {
                case 'head':
                    $method = 'get';
                    break;
                case 'options':
                    $params = new ParameterBag(['action' => $action]);
                    $action = 'generic';
                    break;
            }

            $action = sprintf('%s/%s', $method, $action);
        }

        return parent::dispatchClass(
            $controllerClass,
            $action,
            $responseType,
            $params,
            $sectionContext,
            $controller,
            $previousReply
        );
    }

    public function getRouter()
    {
        if (!$this->router) {
            $this->router = $this->app->router('api');
        }

        return $this->router;
    }

    public function render(AbstractReply $reply, $responseType)
    {
        // TODO: supports other response type?
        $responseType = 'json';

        /** @var Json $renderer */
        $renderer = $this->app->renderer($responseType);
        $renderer->getResponse()->header('Last-Modified', gmdate('D, d M Y H:i:s', \XF::$time) . ' GMT', true);
        $renderer->setResponseCode($reply->getResponseCode());

        if ($reply instanceof Error) {
            $content = $renderer->renderErrors($reply->getErrors());
        } elseif ($reply instanceof Message) {
            $content = $renderer->renderMessage($reply->getMessage());
        } elseif ($reply instanceof Redirect) {
            $content = $renderer->renderRedirect($reply->getUrl(), $reply->getType(), $reply->getMessage());
        } elseif ($reply instanceof Reply) {
            $content = $reply->getData();
        } else {
            if (\XF::$debugMode) {
                \XF::dump($reply);
                exit;
            }

            throw new \InvalidArgumentException('Unsupported reply type: ' . get_class($reply));
        }

        $content = $this->app->renderPage($content, $reply, $renderer);
        $content = $renderer->postFilter($content, $reply);

        $response = $renderer->getResponse();

        $response->body($content);

        return $response;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Dispatcher extends \XF\Mvc\Dispatcher
    {
        // extension hint
    }
}

<?php

namespace Xfrocks\Api\XF\Mvc\Renderer;

use XF\Db\AbstractAdapter;
use XF\Mvc\Renderer\Html;
use XF\Mvc\Reply\AbstractReply;
use Xfrocks\Api\XF\Session\Session;

class Json extends XFCP_Json
{
    public function postFilter($content, AbstractReply $reply)
    {
        $json = parent::postFilter($content, $reply);

        if (\XF::$debugMode) {
            $app = \XF::app();
            $container = $app->container();
            $language = $app->language();

            if ($container->isCached('db')) {
                /** @var AbstractAdapter $db */
                $db = $container['db'];
                $dbQueries = $db->getQueryCount();
            } else {
                $dbQueries = 'N/A';
            }

            $pageTime = microtime(true) - $container['time.granular'];

            $this->response->header('Api-Debug-Db-Queries', $language->numberFormat($dbQueries));
            $this->response->header('Api-Debug-Memory-Usage', $language->fileSizeFormat(memory_get_usage()));
            $this->response->header('Api-Debug-Memory-Peak', $language->fileSizeFormat(memory_get_peak_usage()));
            $this->response->header('Api-Debug-Page-Time', $language->numberFormat($pageTime, 6));
        }

        return $json;
    }

    public function renderErrors(array $errors)
    {
        return [
            'status' => 'error',
            'errors' => $errors
        ];
    }

    public function renderRedirect($url, $type, $message = '')
    {
        /** @var Html $htmlRenderer */
        $htmlRenderer = \XF::app()->renderer('html');
        $htmlRenderer->renderRedirect($url, $type, $message);

        return parent::renderRedirect($url, $type, $message);
    }

    protected function addDefaultJsonParams(array $content)
    {
        $visitor = \XF::visitor();
        if ($visitor['user_id'] > 0) {
            $content['system_info']['time'] = \XF::$time;
            $content['system_info']['visitor_id'] = $visitor['user_id'];
        }

        if (\XF::$debugMode) {
            $app = \XF::app();
            $container = $app->container();

            $pageUrl = $app->request()->getFullRequestUri();
            $debugUrl = $pageUrl . (strpos($pageUrl, '?') !== false ? '&' : '?') . '_debug=1';
            $content['system_info']['debug_url'] = $debugUrl;

            if ($container->isCached('session')) {
                /** @var Session $session */
                $session = $container['session'];
                $token = $session->getToken();
                if (!empty($token)) {
                    $content['system_info']['client_id'] = $token->client_id;
                    $content['system_info']['token_text'] = $token->token_text;
                    $content['system_info']['expire_date'] = $token->expire_date;
                    $content['system_info']['scope'] = $token->scope;
                }
            }
        }

        return $content;
    }

    protected function prepareJsonEncode($value)
    {
        $value = parent::prepareJsonEncode($value);

        if (is_array($value)) {
            foreach (array_keys($value) as $key) {
                if ($value[$key] === null) {
                    unset($value[$key]);
                }
            }
        }

        return $value;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Json extends \XF\Mvc\Renderer\Json
    {
        // extension hint
    }
}

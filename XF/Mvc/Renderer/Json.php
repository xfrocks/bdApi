<?php

namespace Xfrocks\Api\XF\Mvc\Renderer;

use XF\Db\AbstractAdapter;
use XF\Mvc\Renderer\Html;

class Json extends XFCP_Json
{
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

            if ($container->isCached('db')) {
                /** @var AbstractAdapter $db */
                $db = $container['db'];
                $dbQueries = $db->getQueryCount();
            } else {
                $dbQueries = null;
            }

            $pageUrl = $app->request()->getFullRequestUri();
            $debugUrl = $pageUrl . (strpos($pageUrl, '?') !== false ? '&' : '?') . '_debug=1';

            $content['debug'] = [
                'db_queries' => $dbQueries,
                'debug_url' => $debugUrl,
                'memory_usage' => memory_get_usage(),
                'memory_peak' => memory_get_peak_usage(),
                'page_time' => microtime(true) - $container['time.granular']
            ];
        }

        return $content;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Json extends \XF\Mvc\Renderer\Json
    {
        // extension hint
    }
}

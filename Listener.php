<?php

namespace Xfrocks\Api;

use XF\Entity\User;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;
use XF\Util\Php;
use Xfrocks\Api\Repository\Subscription;

class Listener
{
    public static $apiDirName = 'api';
    public static $scopeDelimiter = ' ';
    public static $accessTokenParamKey = 'oauth_token';

    /**
     * @param \XF\Pub\App $app
     */
    public static function appPubSetup($app)
    {
        $container = $app->container();

        $container['api.server'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\OAuth2\Server');
            return new $class($app);
        };

        $container['api.transformer'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Transformer');
            return new $class($app);
        };

        $container['router.api'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Mvc\Router');
            return new $class($app);
        };

        $addOnCache = $container['addon.cache'];
        $extension = $app->extension();
        if (!empty($addOnCache['XFRM'])) {
            $extension->addClassExtension('Xfrocks\Api\Data\Modules', 'Xfrocks\Api\XFRM\Data\Modules');
        }
    }

    public static function onUserAlertPostSave(Entity $entity)
    {
        if (!($entity instanceof UserAlert) || !$entity->isInsert()) {
            return;
        }

        static $userOptions = [];

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');

        if (Subscription::getSubscription(Subscription::TYPE_NOTIFICATION)) {
            if ($entity->alerted_user_id > 0) {
                $subColumn = \XF::options()->bdApi_subscriptionColumnUserNotification;
                if (!isset($userOptions[$entity->alerted_user_id])) {
                    $userOptions[$entity->alerted_user_id] = $entity->db()->fetchOne('
                        SELECT `' . $subColumn . '`
                        FROM `xf_user_option`
                        WHERE user_id = ?
                    ', $entity->alerted_user_id);
                }

                $option = $userOptions[$entity->alerted_user_id];
                if (!empty($option)) {
                    $option = Php::safeUnserialize($option);
                }

                if (empty($option)) {
                    $option = [];
                }
            } else {
                $option = $subRepo->getClientSubscriptionsData();
            }

            if (!empty($option)) {
                $subRepo->ping(
                    $option,
                    'insert',
                    Subscription::TYPE_NOTIFICATION,
                    $entity->alert_id
                );
            }
        }
    }
}

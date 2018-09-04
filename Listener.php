<?php

namespace Xfrocks\Api;

use XF\Entity\Post;
use XF\Entity\Thread;
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

        if ($container->offsetExists('api.server')) {
            // temporary workaround for XF2 job.php weird-behavior
            // https://xenforo.com/community/threads/job-php-runs-app-setup-twice.153198/
            // TODO: implement permanent solution or remove this after XF is updated
            return;
        }

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

    public static function onEntityUserAlertPostSave(Entity $entity)
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

    public static function onEntityPostPostSave(Entity $entity)
    {
        if (!($entity instanceof Post)) {
            return;
        }

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');

        if ($entity->isInsert()) {
            $subRepo->pingThreadPost('insert', $entity);
        } elseif ($entity->isChanged('message_state')) {
            if ($entity->message_state === 'visible') {
                $subRepo->pingThreadPost('insert', $entity);
            } elseif ($entity->getExistingValue('message') === 'visible') {
                $subRepo->pingThreadPost('delete', $entity);
            }
        } else {
            $subRepo->pingThreadPost('update', $entity);
        }
    }

    public static function onEntityPostPostDelete(Entity $entity)
    {
        if (!($entity instanceof Post)) {
            return;
        }

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');
        if ($entity->message_state === 'visible') {
            $subRepo->pingThreadPost('delete', $entity);
        }
    }

    public static function onThreadEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $subColumn = \XF::options()->bdApi_subscriptionColumnThreadPost;
        if (empty($subColumn)) {
            return;
        }

        $structure->columns[$subColumn] = ['type' => Entity::SERIALIZED_ARRAY, 'default' => []];
    }

    public static function onEntityThreadPostDelete(Entity $entity)
    {
        if (!($entity instanceof Thread)) {
            return;
        }

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');
        $subRepo->deleteSubscriptionsForTopic(
            Subscription::TYPE_THREAD_POST,
            $entity->thread_id
        );
    }

    public static function onEntityUserOptionStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $userColumn = \XF::options()->bdApi_subscriptionColumnUser;
        $userNotifyColumn = \XF::options()->bdApi_subscriptionColumnUserNotification;

        if (!empty($userColumn)) {
            $structure->columns[$userColumn] = ['type' => Entity::SERIALIZED_ARRAY, 'default' => []];
        }

        if (!empty($userNotifyColumn)) {
            $structure->columns[$userNotifyColumn] = ['type' => Entity::SERIALIZED_ARRAY, 'default' => []];
        }
    }

    public static function onEntityUserPostSave(Entity $entity)
    {
        if (!($entity instanceof User)) {
            return;
        }

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');

        if ($entity->isInsert()) {
            $subRepo->pingUser('insert', $entity);
        }
    }

    public static function onEntityUserPostDelete(Entity $entity)
    {
        if (!($entity instanceof User)) {
            return;
        }

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');
        $subRepo->pingUser('delete', $entity);

        $subRepo->deleteSubscriptionsForTopic(
            Subscription::TYPE_USER,
            $entity->user_id
        );
    }
}

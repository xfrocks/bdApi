<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class UserAlert extends AbstractHandler
{
    const KEY_ID = 'notification_id';
    const KEY_CREATE_DATE = 'notification_create_date';
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_CONTENT_TYPE = 'content_type';
    const KEY_CONTENT_ID = 'content_id';
    const KEY_CONTENT_ACTION = 'content_action';

    const DYNAMIC_KEY_IS_UNREAD = 'notification_is_unread';
    const DYNAMIC_KEY_NOTIFICATION_TYPE = 'notification_type';
    const DYNAMIC_KEY_NOTIFICATION_HTML = 'notification_html';

    public function getMappings(TransformContext $context)
    {
        return [
            'alert_id' => self::KEY_ID,
            'event_date' => self::KEY_CREATE_DATE,
            'user_id' => self::KEY_CREATOR_USER_ID,
            'username' => self::KEY_CREATOR_USERNAME,
            'content_type' => self::KEY_CONTENT_TYPE,
            'content_id' => self::KEY_CONTENT_ID,
            'action' => self::KEY_CONTENT_ACTION,


            self::DYNAMIC_KEY_IS_UNREAD,
            self::DYNAMIC_KEY_NOTIFICATION_TYPE,
            self::DYNAMIC_KEY_NOTIFICATION_HTML
        ];
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XF\Entity\UserAlert $alert */
        $alert = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_IS_UNREAD:
                return $alert->isUnviewed();
            case self::DYNAMIC_KEY_NOTIFICATION_TYPE:
                return sprintf(
                    '%s_%d_%s',
                    $alert->content_type,
                    $alert->content_id,
                    $alert->action
                );
            case self::DYNAMIC_KEY_NOTIFICATION_HTML:
                return $alert->render();
        }

        return parent::calculateDynamicValue($context, $key);
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XF\Entity\UserAlert $alert */
        $alert = $context->getSource();

        $links = [];

        $links['content'] = $this->buildApiLink('notifications/content', $alert);
        $links['read'] = $this->buildApiLink('notifications/read');

        /** @var \XF\Entity\User|null $user */
        $user = $alert->User;
        if ($user) {
            $links['creator_avatar'] = $user->getAvatarUrl('m');
        }

        return $links;
    }

    public function onTransformEntities(TransformContext $context, $entities)
    {
        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = $this->app->repository('XF:UserAlert');
        /** @var \XF\Entity\UserAlert[] $entities */
        $userAlertRepo->addContentToAlerts($entities);

        return parent::onTransformEntities($context, $entities);
    }
}

<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ConversationMaster;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use Xfrocks\Api\Util\PageNav;

class Notification extends AbstractController
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $this->assertRegistrationRequired();
    }

    public function actionGetIndex()
    {
        $params = $this
            ->params()
            ->definePageNav();

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $finder = $alertRepo->findAlertsForUser(
            \XF::visitor()->user_id,
            \XF::$time - $this->options()->alertExpiryDays * 86400
        );

        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $notifications = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'notifications' => $notifications,
            'notifications_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'notifications');

        return $this->api($data);
    }

    public function actionPostRead()
    {
        $visitor = \XF::visitor();
        if ($visitor->alerts_unread > 0) {
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->repository('XF:UserAlert');
            $alertRepo->markUserAlertsRead(\XF::visitor());
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetContent(ParameterBag $params)
    {
        /** @var UserAlert $alert */
        $alert = $this->assertRecordExists('XF:UserAlert', $params->alert_id);
        if ($alert->alerted_user_id !== \XF::visitor()->user_id) {
            return $this->noPermission();
        }


        $contentEntity = $this->getContentControllerResponse($alert);
        if ($contentEntity instanceof Entity) {
            if (method_exists($contentEntity, 'canView')) {
                if (!$contentEntity->canView()) {
                    return $this->noPermission();
                }
            }

            $contentEntity = $this->transformEntityLazily($contentEntity);
        } elseif ($contentEntity instanceof Finder) {
            $contentEntity = $this->transformFinderLazily($contentEntity);
        }

        $data = [
            'notification_id' => $alert->alert_id,
            'notification' => $this->transformEntityLazily($alert)
        ];

        if ($contentEntity) {
            $data['content'] = $contentEntity;
        }

        return $this->api($data);
    }

    protected function getContentControllerResponse(UserAlert $alert)
    {
        switch ($alert->content_type) {
            case 'conversation':
                switch ($alert->action) {
                    // TODO: Support special action case
                }

                $visitor = \XF::visitor();

                /** @var \XF\Finder\ConversationUser $finder */
                $finder = $this->finder('XF:ConversationUser');
                $finder->forUser($visitor, false);
                $finder->where('conversation_id', $alert->content_id);

                /** @var \XF\Entity\ConversationUser|null $conversation */
                $conversation = $finder->fetchOne();
                /** @var ConversationMaster|null $convoMaster */
                $convoMaster = $conversation ? $conversation->Master : null;

                return $convoMaster;
            case 'thread':
                return $this->assertRecordExists('XF:Thread', $alert->content_id);
            case 'post':
                return $this->assertRecordExists('XF:Post', $alert->content_id);
            case 'user':
                switch ($alert->action) {
                    case 'following':
                        return $this->rerouteController('Xfrocks\Api\Controller\User', 'get-followers', [
                            'user_id' => $alert->content_id
                        ]);
                    case 'post_copy':
                    case 'post_move':
                    case 'thread_merge':
                        // TODO: Support user alert action (post_copy, post_move, thread_merge)
                        break;
                    case 'thread_move':
                        break;
                }

                return $this->assertRecordExists('XF:User', $alert->content_id);
            case 'profile_post':
                // TODO: Support special action case

                return null;
        }

        return null;
    }
}

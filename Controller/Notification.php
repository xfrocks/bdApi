<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\UserAlert;
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

        $contentControllerResponse = $this->getContentControllerResponse($alert);
        if ($contentControllerResponse !== null) {
            return $contentControllerResponse;
        }

        $data = [
            'notification_id' => $alert->alert_id
        ];

        return $this->api($data);
    }

    protected function getContentControllerResponse(UserAlert $alert)
    {
        switch ($alert->content_type) {
            case 'conversation':
                switch ($alert->action) {
                    case 'insert':
                    case 'join':
                    case 'reply':
                        $this->request()->set('conversation_id', $alert->content_id);

                        return $this->rerouteController('Xfrocks\Api\Controller\ConversationMessage', 'get-index');
                }

                return $this->rerouteController('Xfrocks\Api\Controller\Conversation', 'get-index', [
                    'conversation_id' => $alert->content_id
                ]);
            case 'thread':
                return $this->rerouteController('Xfrocks\Api\Controller\Thread', 'get-index', [
                    'thread_id' => $alert->content_id
                ]);
            case 'post':
                return $this->rerouteController('Xfrocks\Api\Controller\Post', 'get-index', [
                    'post_id' => $alert->content_id
                ]);
            case 'user':
                switch ($alert->action) {
                    case 'following':
                        return $this->rerouteController('Xfrocks\Api\Controller\User', 'get-followers', [
                            'user_id' => $alert->content_id
                        ]);
                    case 'post_copy':
                    case 'post_move':
                    case 'thread_merge':
//                        if (!empty($alert->extra_data['targetLink'])) {
//                            $this->request()->set('link', $alert->extra_data['targetLink']);
//                            return $this->rerouteController('bdApi_ControllerApi_Tool', 'get-parse-link');
//                        }
                        // TODO: Support user alert action (post_copy, post_move, thread_merge)
                        break;
                    case 'thread_move':
                        break;
                }

                return $this->rerouteController('Xfrocks\Api\Controller\User', 'get-index', [
                    'user_id' => $alert->content_id
                ]);
            case 'profile_post':
                $this->request()->set('profile_post_id', $alert->content_id);
                return $this->rerouteController('Xfrocks\Api\Controller\ProfilePost', 'get-comments');
        }

        return null;
    }
}

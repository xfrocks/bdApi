<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\UserAlert;
use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\BatchJob;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\Util\PageNav;
use Xfrocks\Api\Repository\Subscription as RepoSub;

class Notification extends AbstractController
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $this->assertRegistrationRequired();
    }

    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionGetIndex()
    {
        $params = $this
            ->params()
            ->definePageNav();

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $visitor = \XF::visitor();
        $option = $visitor->Option;
        if ($option === null) {
            throw new \RuntimeException('$option === null');
        }

        $finder = $alertRepo->findAlertsForUser(
            $visitor->user_id,
            \XF::$time - $this->options()->alertExpiryDays * 86400
        );

        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $notifications = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'notifications' => $notifications,
            'notifications_total' => $total
        ];

        $topicType = RepoSub::TYPE_NOTIFICATION;
        if (RepoSub::getSubOption($topicType)) {
            /** @var RepoSub $subscriptionRepo */
            $subscriptionRepo = $this->repository('Xfrocks\Api:Subscription');
            $subscriptionRepo->prepareDiscoveryParams(
                $data,
                $topicType,
                $visitor->user_id,
                $this->buildApiLink('notifications', null, ['oauth_token' => '']),
                $option->getValue(RepoSub::getSubColumn($topicType))
            );
        }

        PageNav::addLinksToData($data, $params, $total, 'notifications');

        return $this->api($data);
    }

    /**
     * @return \XF\Mvc\Reply\Message
     */
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

    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetContent(ParameterBag $params)
    {
        /** @var UserAlert $alert */
        $alert = $this->assertRecordExists('XF:UserAlert', $params->alert_id);
        if ($alert->alerted_user_id !== \XF::visitor()->user_id) {
            return $this->noPermission();
        }

        $jobConfig = $this->getBatchJobConfig($alert);
        $contentResponse = null;

        if (is_array($jobConfig)) {
            $jobConfig = array_replace([
                'method' => 'GET',
                'uri' => null,
                'params' => []
            ], $jobConfig);

            $job = new BatchJob($this->app, $jobConfig['method'], $jobConfig['params'], $jobConfig['uri']);
            $contentResponse = $job->execute();
        }

        $data = [
            'notification_id' => $alert->alert_id,
            'notification' => $this->transformEntityLazily($alert)
        ];

        if ($contentResponse !== null) {
            /** @var Transformer $transformer */
            $transformer = $this->app()->container('api.transformer');

            $data = array_merge($data, $transformer->transformBatchJobReply($contentResponse));
        }

        return $this->api($data);
    }

    /**
     * @param UserAlert $alert
     * @return array|null
     */
    protected function getBatchJobConfig(UserAlert $alert)
    {
        switch ($alert->content_type) {
            case 'conversation':
                switch ($alert->action) {
                    case 'insert':
                    case 'join':
                    case 'reply':
                        return [
                            'uri' => 'conversation-messages',
                            'params' => [
                                'conversation_id' => $alert->content_id
                            ]
                        ];
                }

                return [
                    'uri' => 'conversations',
                    'params' => [
                        'conversation_id' => $alert->content_id
                    ]
                ];
            case 'thread':
                return [
                    'uri' => 'threads',
                    'params' => [
                        'thread_id' => $alert->content_id
                    ]
                ];
            case 'post':
                return [
                    'uri' => 'posts',
                    'params' => [
                        'page_of_post_id' => $alert->content_id
                    ]
                ];
            case 'user':
                switch ($alert->action) {
                    case 'following':
                        return [
                            'uri' => 'users/followers',
                            'params' => [
                                'user_id' => $alert->content_id
                            ]
                        ];
                    case 'post_copy':
                    case 'post_move':
                    case 'thread_merge':
                        // TODO: Support user alert action (post_copy, post_move, thread_merge)
                        break;
                    case 'thread_move':
                        break;
                }

                return [
                    'uri' => 'users',
                    'params' => [
                        'user_id' => $alert->content_id
                    ]
                ];
            case 'profile_post':
                return [
                    'uri' => 'profile-posts/comments',
                    'params' => [
                        'profile_post_id' => $alert->content_id
                    ]
                ];
            case 'profile_post_comment':
                return [
                    'uri' => 'profile-posts/comments',
                    'params' => [
                        'page_of_comment_id' => $alert->content_id
                    ]
                ];
        }

        return null;
    }
}

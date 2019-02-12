<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ForumWatch;
use XF\Mvc\ParameterBag;
use Xfrocks\Api\Transform\TransformContext;

class Forum extends AbstractNode
{
    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetFollowed()
    {
        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();
        $finder = $this->finder('XF:ForumWatch')
            ->where('user_id', $visitor->user_id);

        if ($this->request()->exists('total')) {
            $total = $finder->total();

            $data = [
                'forums_total' => $total
            ];

            return $this->api($data);
        }

        $finder->with('Forum');
        /** @var ForumWatch[] $forumWatches */
        $forumWatches = $finder->fetch();

        $forums = [];

        $this->params()->getTransformContext()->onTransformedCallbacks[] = function ($context, &$data) use ($forumWatches) {
            /** @var TransformContext $context */
            $source = $context->getSource();
            if (!($source instanceof \XF\Entity\Forum)) {
                return;
            }

            /** @var ForumWatch|null $watched */
            $watched = null;
            foreach ($forumWatches as $forumWatch) {
                if ($forumWatch->node_id == $source->node_id) {
                    $watched = $forumWatch;

                    break;
                }
            }

            if ($watched) {
                $data['follow'] = [
                    'post' => $watched->notify_on == 'message',
                    'alert' => $watched->send_alert,
                    'email' => $watched->send_email
                ];
            }
        };

        foreach ($forumWatches as $forumWatch) {
            $forums[] = $this->transformEntityLazily($forumWatch->Forum);
        }

        $data = [
            'forums' => $forums
        ];

        return $this->api($data);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetFollowers(ParameterBag $paramBag)
    {
        $forum = $this->assertViewableForum($paramBag->node_id);

        $users = [];
        if ($forum->canWatch()) {
            $visitor = \XF::visitor();

            /** @var ForumWatch|null $watched */
            $watched = $this->em()->findOne('XF:ForumWatch', [
                'user_id' => $visitor->user_id,
                'node_id' => $forum->node_id
            ]);

            if ($watched) {
                $users[] = [
                    'user_id' => $visitor->user_id,
                    'username' => $visitor->username,
                    'follow' => [
                        'post' => $watched->notify_on == 'message',
                        'alert' => $watched->send_alert,
                        'email' => $watched->send_email
                    ]
                ];
            }
        }

        $data = [
            'users' => $users
        ];

        return $this->api($data);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostFollowers(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('post', 'uint', 'whether to receive notification for post')
            ->define('alert', 'uint', 'whether to receive notification as alert', 1)
            ->define('email', 'uint', 'whether to receive notification as email');

        $forum = $this->assertViewableForum($paramBag->node_id);

        $error = null;
        if (!$forum->canWatch($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Repository\ForumWatch $forumWatchRepo */
        $forumWatchRepo = $this->repository('XF:ForumWatch');
        $forumWatchRepo->setWatchState(
            $forum,
            \XF::visitor(),
            $params['post'] > 0 ? 'message' : 'thread',
            $params['alert'],
            $params['email']
        );

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteFollowers(ParameterBag $paramBag)
    {
        $forum = $this->assertViewableForum($paramBag->node_id);

        $error = null;
        if (!$forum->canWatch($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Repository\ForumWatch $forumWatchRepo */
        $forumWatchRepo = $this->repository('XF:ForumWatch');
        $forumWatchRepo->setWatchState($forum, \XF::visitor(), 'delete');

        return $this->message(\XF::phrase('changes_saved'));
    }

    protected function getNodeTypeId()
    {
        return 'Forum';
    }

    protected function getNamePlural()
    {
        return 'forums';
    }

    protected function getNameSingular()
    {
        return 'forum';
    }

    /**
     * @param int $forumId
     * @param array $extraWith
     * @return \XF\Entity\Forum
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableForum($forumId, array $extraWith = [])
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->assertRecordExists('XF:Forum', $forumId, $extraWith);
        if (!$forum->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $forum;
    }
}

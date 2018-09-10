<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ForumWatch;
use XF\Mvc\ParameterBag;

class Forum extends AbstractNode
{
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

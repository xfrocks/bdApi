<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;

class Thread extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->thread_id) {
            return $this->actionSingle($params->thread_id);
        }

        $threads = [];

        $data = [
            'threads' => $this->transformEntitiesLazily($threads),
            'threads_total' => 0
        ];

        return $this->api($data);
    }

    public function actionMultiple(array $ids)
    {
        $threads = [];
        if (count($ids) > 0) {
            $threads = $this->finder('XF:Thread')
                ->whereIds($ids)
                ->fetch()
                ->filterViewable()
                ->sortByList($ids);
        }

        $data = [
            'threads' => $this->transformEntitiesLazily($threads)
        ];

        return $this->api($data);
    }

    public function actionSingle($threadId)
    {
        $thread = $this->assertViewableThread($threadId);

        $data = [
            'thread' => $this->transformEntityLazily($thread)
        ];

        return $this->api($data);
    }

    /**
     * @param $threadId
     * @param array $extraWith
     * @return \XF\Entity\Thread
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableThread($threadId, array $extraWith = [])
    {
        $thread = $this->assertViewableEntity('XF:Thread', $threadId, $extraWith);

        return $thread;
    }
}
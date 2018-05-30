<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;

class User extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->user_id) {
            return $this->actionSingle($params->user_id);
        }

        return $this->notFound();
    }

    protected function actionSingle($userId)
    {
        $user = $this->assertViewableUser($userId);

        $data = [
            'user' => $this->transformEntityLazily($user)
        ];

        return $this->api($data);
    }

    /**
     * @param int $userId
     * @param array $extraWith
     * @return \XF\Entity\User
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableUser($userId, array $extraWith = [])
    {
        /** @var \XF\Entity\User $user */
        $user = $this->assertViewableEntity('XF:User', $userId, $extraWith);

        if (!$user->canViewFullProfile($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $user;
    }
}

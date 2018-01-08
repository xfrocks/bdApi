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
            'user' => $this->transformEntity($user)
        ];

        return $this->api($data);
    }

    protected function assertViewableUser($userId, array $extraWith = [])
    {
        $shortName = 'XF:User';
        $with = array_merge($this->getFetchWith($shortName), $extraWith);
        array_unique($with);

        /** @var \XF\Entity\User $user */
        $user = $this->em()->find($shortName, $userId, $with);
        if (!$user) {
            throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
        }

        if (!$user->canViewFullProfile($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $user;
    }
}

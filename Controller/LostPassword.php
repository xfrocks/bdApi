<?php

namespace Xfrocks\Api\Controller;

use XF\Service\User\PasswordReset;

class LostPassword extends AbstractController
{
    public function actionPostIndex()
    {
        $params = $this->params()
            ->define('username', 'str')
            ->define('email', 'str');

        $usernameOrEmail = $params['username'] ?: $params['email'];
        if (empty($usernameOrEmail)) {
            return $this->error(\XF::phrase('bdapi_slash_lost_password_requires_username_or_email'), 400);
        }

        $token = $this->session()->getToken();
        if (empty($token)) {
            return $this->noPermission();
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        /** @var \XF\Entity\User|null $user */
        $user = $userRepo->getUserByNameOrEmail($usernameOrEmail);
        if (!$user) {
            return $this->error(\XF::phrase('requested_member_not_found'));
        }

        /** @var PasswordReset $passwordReset */
        $passwordReset = $this->service('XF:User\PasswordReset', $user);
        if (!$passwordReset->canTriggerConfirmation($error)) {
            return $this->error($error);
        }

        $passwordReset->triggerConfirmation();
        return $this->message(\XF::phrase('password_reset_request_has_been_emailed_to_you'));
    }
}

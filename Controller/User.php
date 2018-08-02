<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Util\PageNav;

class User extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->user_id) {
            return $this->actionSingle($params->user_id);
        }

        $params = $this
            ->params()
            ->definePageNav();

        /** @var \XF\Finder\User $finder */
        $finder = $this->finder('XF:User');
        $finder->with('Option', true);
        $finder->with('Profile', true);

        $finder->isValidUser();
        $finder->order('user_id');

        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $users = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'users' => $users,
            'users_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'users');

        return $this->api($data);
    }

    public function actionPostIndex()
    {
        $params = $this
            ->params()
            ->define('user_email','str', 'email of the new user')
            ->define('username', 'str', 'username of the new user')
            ->define('password', 'str', 'password of the new user')
            ->define('password_algo', 'str', 'algorithm used to encrypt the password parameter')
            ->define('user_dob_day', 'uint', 'date of birth (day) of the new user')
            ->define('user_dob_month', 'uint', 'date of birth (month) of the new user')
            ->define('user_dob_year', 'uint', 'date of birth (year) of the new user')
            ->define('fields', 'array', 'user field values')
            ->define('client_id', 'str', 'client ID of the Client')
            ->define('extra_data', 'str')
            ->define('extra_timestamp', 'uint');


    }

    public function actionGetMe()
    {
        return $this->actionGetIndex($this->buildParamsForVisitor());
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
        $user = $this->assertRecordExists('XF:User', $userId, $extraWith, 'requested_user_not_found');

        return $user;
    }

    /**
     * @return ParameterBag
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function buildParamsForVisitor()
    {
        $this->assertRegistrationRequired();

        return new ParameterBag(['user_id' => \XF::visitor()->user_id]);
    }
}

<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\UserAuth;
use XF\Entity\UserFollow;
use XF\Entity\UserGroup;
use XF\InputFilterer;
use XF\Mvc\ParameterBag;
use XF\Service\User\Avatar;
use XF\Service\User\Follow;
use XF\Util\Php;
use XF\Validator\Email;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Util\Crypt;
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

        if (!$this->options()->registrationSetup['enabled']) {
            throw $this->exception(
                $this->error(\XF::phrase('new_registrations_currently_not_being_accepted'))
            );
        }

        // prevent discouraged IP addresses from registering
        if ($this->options()->preventDiscouragedRegistration && $this->isDiscouraged()) {
            throw $this->exception(
                $this->error(\XF::phrase('new_registrations_currently_not_being_accepted'))
            );
        }

        $session = $this->session();
        /** @var Token|null $token */
        $token = $this->session()->getToken();
        /** @var Client|null $client */
        $client = $token ? $token->Client : null;

        if (!$client) {
            /** @var Client $client */
            $client = $this->assertRecordExists(
                'Xfrocks\Api:Client',
                $params['client_id'],
                [],
                'bdapi_requested_client_not_found'
            );

            $clientSecret = $client->client_secret;
        } else {
            $clientSecret = $client->client_secret;
        }

        $extraData = [];
        if (!empty($params['extra_data'])) {
            $extraData = Crypt::decryptTypeOne($params['extra_data'], $params['extra_timestamp']);
            if (!empty($extraData)) {
                $extraData = Php::safeUnserialize($extraData);
            }

            if (empty($extraData)) {
                $extraData = [];
            }
        }

        /** @var \XF\Service\User\Registration $registration */
        $registration = $this->service('XF:User\Registration');

        $input = [
            'email' => $params['user_email'],
            'username' => $params['username'],
            'dob_day' => $params['user_dob_day'],
            'dob_month' => $params['user_dob_month'],
            'dob_year' => $params['user_dob_year'],
            'custom_fields' => $params['fields']
        ];

        $password = Crypt::decrypt($params['password'], $params['password_algo'], $clientSecret);
        if (!empty($password)) {
            $input['password'] = $password;
        } else {
            $registration->setNoPassword();
        }

        $allowEmailConfirm = true;
        if (!empty($extraData['user_email'])
            && $extraData['user_email'] == $input['email']
        ) {
            $allowEmailConfirm = false;
        }

        $registration->skipEmailConfirmation(!$allowEmailConfirm);

        $visitor = \XF::visitor();
        if ($visitor->hasAdminPermission('user')
            && $session->hasScope(Server::SCOPE_MANAGE_SYSTEM)
        ) {
            $input['user_state'] = 'valid';
        }

        $registration->setFromInput($input);
        $registration->checkForSpam();

        if (!$registration->validate($errors))
        {
            return $this->error($errors);
        }

        /** @var \XF\Entity\User $user */
        $user = $registration->save();

        if ($visitor->user_id == 0) {
            $session->changeUser($user);
            \XF::setVisitor($user);
        }

        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');
        $scopes = [];
        $scopes[] = Server::SCOPE_READ;
        $scopes[] = Server::SCOPE_POST;
        $scopes[] = Server::SCOPE_MANAGE_ACCOUNT_SETTINGS;
        $scopes[] = Server::SCOPE_PARTICIPATE_IN_CONVERSATIONS;

        $accessToken = $apiServer->newAccessToken(strval($user->user_id), $client, $scopes);

        $data = [
            'user' => $this->transformEntityLazily($user),
            '_user' => $user->toArray(),
            'token' => $accessToken
        ];

        return $this->api($data);
    }

    public function actionPutIndex(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $params = $this
            ->params()
            ->define('password', 'str', 'data of the new password')
            ->define('password_old', 'str', 'data of the existing password')
            ->define('password_algo', 'str', 'algorithm used to encrypt the password and password_old parameters')
            ->define('user_email', 'str', 'new email of the user')
            ->define('username', 'str', 'new username of the user')
            ->define('user_title', 'str', 'new custom title of the user')
            ->define('primary_group_id', 'uint', 'id of new primary group')
            ->define('secondary_group_ids', 'array-uint', 'array of ids of new secondary groups')
            ->define('user_dob_day', 'uint', 'new date of birth (day) of the user')
            ->define('user_dob_month', 'uint', 'new date of birth (month) of the user')
            ->define('user_dob_year', 'uint', 'new date of birth (year) of the user')
            ->define('fields', 'array', 'array of values for user fields');

        $visitor = \XF::visitor();
        $session = $this->session();

        $isAdmin = $session->hasScope(Server::SCOPE_MANAGE_SYSTEM) && $visitor->hasAdminPermission('user');
        $requiredAuth = 0;

        if (!empty($params['password'])) {
            $requiredAuth++;
        }
        if (!empty($params['user_email'])) {
            $requiredAuth++;
        }

        if ($requiredAuth > 0) {
            $isAuth = false;
            if ($isAdmin && $visitor->user_id !== $user->user_id) {
                $isAuth = true;
            } elseif (!empty($params['password_old'])) {
                /** @var \XF\Entity\UserAuth|null $userAuth */
                $userAuth = $user->Auth;
                if ($userAuth) {
                    $passwordOld = Crypt::decrypt($params['password_old'], $params['password_algo']);
                    $authHandler = $userAuth->getAuthenticationHandler();
                    if ($authHandler && $authHandler->hasPassword() && $userAuth->authenticate($passwordOld)) {
                        $isAuth = true;
                    }
                }
            }

            if (!$isAuth) {
                return $this->error(\XF::phrase('bdapi_slash_users_requires_password_old'), 403);
            }
        }

        if ($isAdmin) {
            $user->setOption('admin_edit', true);
        }

        if (!empty($params['password'])) {
            $password = Crypt::decrypt($params['password'], $params['password_algo']);
            /** @var UserAuth $userAuth */
            $userAuth = $user->getRelationOrDefault('Auth');
            $userAuth->setPassword($password);
        }

        if (!empty($params['user_email'])) {
            $user->email = $params['user_email'];
            $options = $this->options();

            if ($user->isChanged('email')
                && $options->registrationSetup['emailConfirmation']
            ) {
                switch ($user->user_state) {
                    case 'moderated':
                    case 'email_confirm':
                        $user->user_state = 'email_confirm';
                        break;
                    default:
                        $user->user_state = 'email_confirm_edit';
                }
            }
        }

        if (!empty($params['username'])) {
            $user->username = $params['username'];
            if ($user->isChanged('username') && !$isAdmin) {
                return $this->error(\XF::phrase('bdapi_slash_users_denied_username'), 403);
            }
        }

        if ($this->request()->exists('user_title')) {
            $user->custom_title = $params['user_title'];
            if ($user->isChanged('custom_title')
                && !$isAdmin
            ) {
                return $this->error(\XF::phrase('bdapi_slash_users_denied_user_title'), 403);
            }
        }

        if ($params['primary_group_id'] > 0) {
            /** @var UserGroup[] $userGroups */
            $userGroups = $this->finder('XF:UserGroup')->fetch();

            if (!isset($userGroups[$params['primary_group_id']])) {
                return $this->notFound(\XF::phrase('requested_user_group_not_found'));
            }

            if (!empty($params['secondary_group_ids'])) {
                foreach ($params['secondary_group_ids'] as $secondaryGroupId) {
                    if (!isset($userGroups[$secondaryGroupId])) {
                        return $this->notFound(\XF::phrase('requested_user_group_not_found'));
                    }
                }
            }

            $user->user_group_id = $params['primary_group_id'];

            $secondaryGroupIds = $params['secondary_group_ids'];
            $secondaryGroupIds = array_map('intval', $secondaryGroupIds);
            $secondaryGroupIds = array_unique($secondaryGroupIds);
            sort($secondaryGroupIds, SORT_NUMERIC);

            $zeroKey = array_search(0, $secondaryGroupIds);
            if ($zeroKey !== false) {
                unset($secondaryGroupIds[$zeroKey]);
            }

            $user->secondary_group_ids = $secondaryGroupIds;
        }

        if (!empty($params['user_dob_day']) && !empty($params['user_dob_month']) && !empty($params['user_dob_year'])) {
            $user->Profile->setDob($params['user_dob_day'], $params['user_dob_month'], $params['user_dob_year']);

            $hasExistingDob = false;
            $hasExistingDob = $hasExistingDob || !!$user->Profile->getExistingValue('dob_day');
            $hasExistingDob = $hasExistingDob || !!$user->Profile->getExistingValue('dob_month');
            $hasExistingDob = $hasExistingDob || !!$user->Profile->getExistingValue('dob_year');

            if ($hasExistingDob
                && (
                    $user->Profile->isChanged('dob_day')
                    || $user->Profile->isChanged('dob_month')
                    || $user->Profile->isChanged('dob_year')
                )
                && !$isAdmin
            ) {
                return $this->error(\XF::phrase('bdapi_slash_users_denied_dob'), 403);
            }
        }

        if (!empty($params['fields'])) {
            $inputFilter = $this->app()->inputFilterer();
            $profileFields = $inputFilter->filterArray($params['fields'], [
                'about' => 'str',
                'homepage' => 'str',
                'location' => 'str',
                'occupation' => 'str'
            ]);

            $user->Profile->bulkSet($profileFields);
            $user->Profile->custom_fields->bulkSet($params['fields']);
        }

        $user->preSave();

        if (!$isAdmin) {
            if ($user->isChanged('user_group_id')
                || $user->isChanged('secondary_group_ids')
            ) {
                return $this->error(\XF::phrase('bdapi_slash_users_denied_user_group'), 403);
            }
        }

        $shouldSendEmailConfirmation = false;
        if ($user->isChanged('email')
            && in_array($user->user_state, ['email_confirm', 'email_confirm_edit'])
        ) {
            $shouldSendEmailConfirmation = true;
        }

        if ($user->hasErrors()) {
            return $this->error($user->getErrors());
        }

        $user->save();

        if ($shouldSendEmailConfirmation) {
            /** @var \XF\Service\User\EmailConfirmation $emailConfirmation */
            $emailConfirmation = $this->service('XF:User\EmailConfirmation', $user);
            $emailConfirmation->triggerConfirmation();
        }

        return $this->message(\XF::phrase('saved_changes'));
    }

    public function actionGetFields()
    {
        $finder = $this->finder('XF:UserField');

        $userFields = $this->transformFinderLazily($finder);

        $data = [
            'fields' => $userFields
        ];

        return $this->api($data);
    }

    public function actionGetFind()
    {
        $params = $this
            ->params()
            ->define('username', 'str', 'username to filter')
            ->define('user_email', 'str', 'email to filter');

        /** @var \XF\Finder\User $userFinder */
        $userFinder = $this->finder('XF:User');

        $users = [];

        if (!empty($params['user_email'])) {
            /** @var Email $emailValidator */
            $emailValidator = \XF::app()->validator('XF:Email');
            if ($emailValidator->isValid($params['user_email'])
                && \XF::visitor()->hasAdminPermission('user')
                && $this->session()->hasScope(Server::SCOPE_MANAGE_SYSTEM)
            ) {
                $userFinder->where('email', $params['user_email']);

                $total = $userFinder->total();
                $users = $total > 0 ? $this->transformFinderLazily($userFinder) : [];
            }
        }

        if (empty($users) && utf8_strlen($params['username']) >= 0) {
            $userFinder->where('username', 'like', $userFinder->escapeLike($params['username'], '?%'));

            $total = $userFinder->total();
            $users = $total > 0 ? $this->transformFinderLazily($userFinder->limit(10)) : [];
        }

        $data = [
            'users' => $users
        ];

        return $this->api($data);
    }

    public function actionGetMe()
    {
        return $this->actionGetIndex($this->buildParamsForVisitor());
    }

    public function actionPostAvatar(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $params = $this
            ->params()
            ->define('avatar', 'file', 'binary data of the avatar');

        if ($user->user_id !== \XF::visitor()->user_id) {
            return $this->noPermission();
        }

        if (!$user->canUploadAvatar()) {
            return $this->noPermission();
        }

        if (empty($params['avatar'])) {
            return $this->error(\XF::phrase('bdapi_requires_upload_x', [
                'field' => 'avatar'
            ]), 400);
        }

        /** @var Avatar $avatar */
        $avatar = $this->service('XF:User\Avatar', $user);
        $avatar->setImageFromUpload($params['avatar']);

        $avatar->updateAvatar();

        return $this->message(\XF::phrase('upload_completed_successfully'));
    }

    public function actionDeleteAvatar(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        if ($user->user_id != \XF::visitor()->user_id) {
            return $this->noPermission();
        }

        if (!$user->canUploadAvatar()) {
            return $this->noPermission();
        }

        /** @var Avatar $avatar */
        $avatar = $this->service('XF:User\Avatar', $user);
        $avatar->deleteAvatar();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetFollowers(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        /** @var \XF\Repository\UserFollow $userFollowRepo */
        $userFollowRepo = $this->repository('XF:UserFollow');
        $userFollowersFinder = $userFollowRepo->findFollowersForProfile($user);

        if ($this->request()->exists('total')) {
            $data = [
                'users_total' => $userFollowersFinder->total()
            ];

            return $this->api($data);
        }

        $data = [
            'users' => []
        ];

        /** @var UserFollow $userFollow */
        foreach ($userFollowersFinder->fetch() as $userFollow) {
            $data['users'][] = [
                'user_id' => $userFollow->User->user_id,
                'username' => $userFollow->User->username
            ];
        }

        return $this->api($data);
    }

    public function actionPostFollowers(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $visitor = \XF::visitor();
        if (!$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        /** @var Follow $follow */
        $follow = $this->service('XF:User\Follow', $user);
        $follow->follow();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteFollowers(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $visitor = \XF::visitor();
        if (!$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        /** @var Follow $follow */
        $follow = $this->service('XF:User\Follow', $user);
        $follow->unfollow();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetFollowings(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        /** @var \XF\Repository\UserFollow $userFollowRepo */
        $userFollowRepo = $this->repository('XF:UserFollow');
        $userFollowingFinder = $userFollowRepo->findFollowingForProfile($user);

        if ($this->request()->exists('total')) {
            $data = [
                'users_total' => $userFollowingFinder->total()
            ];

            return $this->api($data);
        }

        $data = [
            'users' => []
        ];

        /** @var UserFollow $userFollow */
        foreach ($userFollowingFinder->fetch() as $userFollow) {
            $data['users'][] = [
                'user_id' => $userFollow->FollowUser->user_id,
                'username' => $userFollow->FollowUser->username
            ];
        }

        return $this->api($data);
    }

    protected function actionSingle($userId)
    {
        $user = $this->assertViewableUser($userId);

        $data = [
            'user' => $this->transformEntityLazily($user)
        ];

        return $this->api($data);
    }

    protected function getDefaultApiScopeForAction($action)
    {
        if ($action === 'PostIndex') {
            $session = $this->session();
            /** @var Token|null $token */
            $token = $session->getToken();
            if (!$token || !$token->client_id) {
                return null;
            }
        }

        return parent::getDefaultApiScopeForAction($action);
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

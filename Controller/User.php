<?php

namespace Xfrocks\Api\Controller;

use OAuth\OAuth2\Token\StdOAuth2Token;
use XF\Entity\ConnectedAccountProvider;
use XF\Entity\UserAuth;
use XF\Entity\UserFollow;
use XF\Entity\UserGroup;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Repository\ConnectedAccount;
use XF\Service\User\Avatar;
use XF\Service\User\Follow;
use XF\Service\User\Ignore;
use XF\Util\Php;
use XF\Validator\Email;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transformer;
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
            ->define('user_email', 'str', 'email of the new user')
            ->define('email', 'str', 'email of the new user (deprecated)')
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
            'email' => $params['user_email'] ?: $params['email'],
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

        $skipEmailConfirmation = false;
        if (!empty($extraData['user_email'])
            && $extraData['user_email'] == $input['email']
        ) {
            $skipEmailConfirmation = true;
        }

        $registration->skipEmailConfirmation($skipEmailConfirmation);

        $visitor = \XF::visitor();
        if ($visitor->hasAdminPermission('user')) {
            $input['user_state'] = 'valid';
        }

        $registration->setFromInput($input);
        $registration->checkForSpam();

        if (!$registration->validate($errors)) {
            return $this->error($errors);
        }

        /** @var \XF\Entity\User $user */
        $user = $registration->save();

        if ($visitor->user_id == 0) {
            $session->changeUser($user);
            \XF::setVisitor($user);
        }

        if (!empty($extraData['external_provider'])
            && !empty($extraData['external_provider_key'])
            && !empty($extraData['access_token'])
        ) {
            /** @var ConnectedAccountProvider|null $provider */
            $provider = $this->em()->find('XF:ConnectedAccountProvider', $extraData['external_provider']);
            $handler = $provider ? $provider->getHandler() : null;
            if ($handler && $provider) {
                $tokenObj = new StdOAuth2Token();
                $tokenObj->setAccessToken($extraData['access_token']);

                $storageState = $handler->getStorageState($provider, $user);
                $storageState->storeToken($tokenObj);
                
                $providerData = $handler->getProviderData($storageState);

                /** @var ConnectedAccount $connectedAccountRepo */
                $connectedAccountRepo = $this->repository('XF:ConnectedAccount');
                $connectedAccountRepo->associateConnectedAccountWithUser($user, $providerData);
            }
        }

        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');
        $scopes = $apiServer->getScopeDefaults();
        $accessToken = $apiServer->newAccessToken($user->user_id, $client, $scopes);
        $refreshToken = $apiServer->newRefreshToken($user->user_id, $client, $scopes);

        $data = [
            'user' => $this->transformEntityLazily($user),
            'token' => \Xfrocks\Api\Util\Token::transformLibAccessTokenEntity($accessToken, $refreshToken),
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
        $isAdmin = $visitor->hasAdminPermission('user');
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
            $user->addCascadedSave($userAuth);
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
                return $this->notFound(\XF::phrase('bdapi_requested_user_group_not_found'));
            }

            if (!empty($params['secondary_group_ids'])) {
                foreach ($params['secondary_group_ids'] as $secondaryGroupId) {
                    if (!isset($userGroups[$secondaryGroupId])) {
                        return $this->notFound(\XF::phrase('bdapi_requested_user_group_not_found'));
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
            if (!!$user->Profile->getExistingValue('dob_day')
                || !!$user->Profile->getExistingValue('dob_month')
                || !!$user->Profile->getExistingValue('dob_year')) {
                $hasExistingDob = true;
            }

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

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionPostPassword(ParameterBag $params)
    {
        $this->params()->markAsDeprecated();
        return $this->actionPutIndex($params);
    }

    public function actionGetDefaultAvatar(ParameterBag $paramBag)
    {
        $avatarSizeMap = $this->app->container('avatarSizeMap');
        $sizes = implode(', ', array_keys($avatarSizeMap));

        $params = $this->params()
            ->define('size', 'str', "Avatar size ({$sizes})", 'l');

        $user = $this->assertViewableUser($paramBag->user_id);

        if (!isset($avatarSizeMap[$params['size']])) {
            return $this->noPermission();
        }
        $size = $avatarSizeMap[$params['size']];

        $viewParams = [
            'user' => $user,
            'size' => $size
        ];

        $this->setResponseType('raw');
        return $this->view('Xfrocks\Api\View\User\DefaultAvatar', '', $viewParams);
    }

    public function actionGetFields()
    {
        $app = $this->app;

        /** @var \XF\CustomField\DefinitionSet $definitionSet */
        $definitionSet = $app->container('customFields.users');

        /** @var Transformer $transformer */
        $transformer = $app->container('api.transformer');

        $context = $this->params()->getTransformContext();
        $fields = $transformer->transformCustomFieldDefinitionSet($context, $definitionSet, '');

        $data = [
            'fields' => $fields
        ];

        return $this->api($data);
    }

    public function actionGetFind()
    {
        $params = $this
            ->params()
            ->define('username', 'str', 'username to filter')
            ->define('user_email', 'str', 'email to filter')
            ->define('email', 'str', 'email to filter (deprecated)');

        /** @var \XF\Finder\User $userFinder */
        $userFinder = $this->finder('XF:User');
        $userFinder->isValidUser();

        $users = [];

        $email = $params['user_email'] ?: $params['email'];
        if (!empty($email)) {
            if (!\XF::visitor()->hasAdminPermission('user')) {
                return $this->noPermission();
            }

            /** @var Email $emailValidator */
            $emailValidator = \XF::app()->validator('XF:Email');
            if ($emailValidator->isValid($email)) {
                $userFinder->where('email', $email);
                $users = $this->transformFinderLazily($userFinder);
            }
        } elseif (strlen($params['username']) > 0) {
            $userFinder->where('username', 'like', $userFinder->escapeLike($params['username'], '?%'));
            $users = $this->transformFinderLazily($userFinder->limit(10));
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

    public function actionPutMe()
    {
        return $this->actionPutIndex($this->buildParamsForVisitor());
    }

    public function actionPostMeAvatar()
    {
        return $this->actionPostAvatar($this->buildParamsForVisitor());
    }

    public function actionDeleteMeAvatar()
    {
        return $this->actionDeleteAvatar($this->buildParamsForVisitor());
    }

    public function actionGetMeFollowers()
    {
        return $this->actionGetFollowers($this->buildParamsForVisitor());
    }

    public function actionGetMeFollowings()
    {
        return $this->actionGetFollowings($this->buildParamsForVisitor());
    }

    public function actionGetMeGroups()
    {
        return $this->actionGetGroups($this->buildParamsForVisitor());
    }

    public function actionPostReport(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('message', 'str', 'reason of the report');

        $user = $this->assertViewableUser($paramBag->user_id);

        if (!$user->canBeReported($error)) {
            return $this->noPermission();
        }

        /** @var \XF\Service\Report\Creator $creator */
        $creator = $this->service('XF:Report\Creator', 'user', $user);
        $creator->setMessage($params['message']);
        
        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionPostAvatar(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $params = $this
            ->params()
            ->defineFile('avatar', 'binary data of the avatar');

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

    public function actionGetIgnored()
    {
        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();

        /** @var Finder|null $finder */
        $finder = null;
        if ($visitor->Profile->ignored) {
            $finder = $this->finder('XF:User')
                ->where('user_id', array_keys($visitor->Profile->ignored))
                ->order('username');
        }

        if ($this->request()->exists('total')) {
            $data = [
                'users_total' => $finder ? $finder->total() : 0
            ];

            return $this->api($data);
        }

        $data = [
            'users' => []
        ];

        /** @var \XF\Entity\User $user */
        foreach ($finder->fetch() as $user) {
            $data['users'][] = [
                'user_id' => $user->user_id,
                'username' => $user->username
            ];
        }

        return $this->api($data);
    }

    public function actionPostIgnore(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        if (!\XF::visitor()->canIgnoreUser($user, $error)) {
            return $this->noPermission($error);
        }

        /** @var Ignore $ignore */
        $ignore = $this->service('XF:User\Ignore', $user);
        $ignore->ignore();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteIgnore(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        if (!\XF::visitor()->canIgnoreUser($user, $error)) {
            return $this->noPermission($error);
        }

        /** @var Ignore $ignore */
        $ignore = $this->service('XF:User\Ignore', $user);
        $ignore->unignore();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetGroups(ParameterBag $params)
    {
        if ($params->user_id) {
            $user = $this->assertViewableUser($params->user_id);

            $userGroupIds = $user->secondary_group_ids;
            $userGroupIds[] = $user->user_group_id;

            $finder = $this->finder('XF:UserGroup');
            $finder->whereIds($userGroupIds);
        } else {
            if (!\XF::visitor()->hasAdminPermission('user')) {
                return $this->noPermission();
            }

            $user = null;
            $finder = $this->finder('XF:UserGroup');
        }

        $this->params()->getTransformContext()->onTransformedCallbacks[] = function ($context, array &$data) use ($user) {
            $source = $context->getSource();
            if (!($source instanceof UserGroup)) {
                return;
            }

            if ($user) {
                $data['is_primary_group'] = $source->user_group_id == $user->user_group_id;
            }
        };

        $data = [
            'user_groups' => $this->transformFinderLazily($finder)
        ];

        if (!empty($user)) {
            $data['user_id'] = $user->user_id;
        }

        return $this->api($data);
    }

    public function actionGetTimeline(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        if (!$user->canViewProfilePosts($error)) {
            return $this->noPermission($error);
        }

        return $this->rerouteController('Xfrocks\Api:Search', 'user-timeline', $params);
    }

    public function actionPostTimeline(ParameterBag $params)
    {
        return $this->rerouteController('Xfrocks\Api:ProfilePost', 'post-index', $params);
    }

    public function actionGetMeTimeline()
    {
        return $this->actionGetTimeline($this->buildParamsForVisitor());
    }

    public function actionPostMeTimeline()
    {
        return $this->actionPostTimeline($this->buildParamsForVisitor());
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

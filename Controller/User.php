<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
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

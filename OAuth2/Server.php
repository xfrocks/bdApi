<?php

namespace Xfrocks\Api\OAuth2;

use League\OAuth2\Server\AbstractServer;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Util\SecureKey;
use Symfony\Component\HttpFoundation\Request;
use XF\Container;
use XF\Mvc\Controller;
use Xfrocks\Api\App;
use Xfrocks\Api\Controller\OAuth2;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Entity\AccessTokenHybrid;
use Xfrocks\Api\OAuth2\Entity\ClientHybrid;
use Xfrocks\Api\OAuth2\Grant\ImplicitGrant;
use Xfrocks\Api\OAuth2\Storage\AccessTokenStorage;
use Xfrocks\Api\OAuth2\Storage\AuthCodeStorage;
use Xfrocks\Api\OAuth2\Storage\ClientStorage;
use Xfrocks\Api\OAuth2\Storage\RefreshTokenStorage;
use Xfrocks\Api\OAuth2\Storage\ScopeStorage;
use Xfrocks\Api\OAuth2\Storage\SessionStorage;
use Xfrocks\Api\Util\Crypt;
use Xfrocks\Api\XF\Pub\Controller\Account;

class Server
{
    const SCOPE_READ = 'read';
    const SCOPE_POST = 'post';
    const SCOPE_MANAGE_ACCOUNT_SETTINGS = 'usercp';
    const SCOPE_PARTICIPATE_IN_CONVERSATIONS = 'conversate';
    const SCOPE_MANAGE_SYSTEM = 'admincp';

    /**
     * @var App
     */
    protected $app;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var bool
     */
    protected $parsedRequest = false;

    /**
     * @param App $app
     */
    public function __construct($app)
    {
        require_once(dirname(__DIR__) . '/vendor/autoload.php');

        $this->app = $app;

        $this->container = new Container();

        $this->container['grant.auth_code'] = function () {
            $authCode = new AuthCodeGrant();
            $authCode->setAuthTokenTTL($this->getOptionAuthCodeTTL());

            return $authCode;
        };

        $this->container['grant.client_credentials'] = function () {
            return new ClientCredentialsGrant();
        };

        $this->container['grant.implicit'] = function () {
            return new ImplicitGrant();
        };

        $this->container['grant.password'] = function () {
            return new PasswordGrant();
        };

        $this->container['grant.refresh_token'] = function () {
            $refreshToken = new RefreshTokenGrant();
            $refreshToken->setRefreshTokenTTL($this->getOptionRefreshTokenTTL());

            return $refreshToken;
        };

        $this->container['request'] = function (Container $c) {
            $request = Request::createFromGlobals();

            // TODO: verify whether using token from query for all requests violates OAuth2 spec
            $queryAccessToken = $request->query->get(Listener::$accessTokenParamKey);
            if (!empty($queryAccessToken)) {
                $bodyAccessToken = $request->request->get(Listener::$accessTokenParamKey);
                if (empty($bodyAccessToken)) {
                    $request->request->set(Listener::$accessTokenParamKey, $queryAccessToken);
                }
            }

            return $request;
        };

        $this->container['server.auth'] = function (Container $c) {
            $authorizationServer = new AuthorizationServer();
            $authorizationServer->setAccessTokenTTL($this->getOptionAccessTokenTTL())
                ->setDefaultScope(self::SCOPE_READ)
                ->setScopeDelimiter(Listener::$scopeDelimiter)
                ->addGrantType($c['grant.auth_code'])
                ->addGrantType($c['grant.client_credentials'])
                ->addGrantType($c['grant.password'])
                ->addGrantType($c['grant.implicit'])
                ->addGrantType($c['grant.refresh_token'])
                ->setAccessTokenStorage($c['storage.access_token'])
                ->setAuthCodeStorage($c['storage.auth_code'])
                ->setClientStorage($c['storage.client'])
                ->setRefreshTokenStorage($c['storage.refresh_token'])
                ->setRequest($c['request'])
                ->setScopeStorage($c['storage.scope'])
                ->setSessionStorage($c['storage.session']);

            return $authorizationServer;
        };

        $this->container['server.resource'] = function (Container $c) {
            $resourceServer = new ResourceServer(
                $c['storage.session'],
                $c['storage.access_token'],
                $c['storage.client'],
                $c['storage.scope']
            );
            $resourceServer->setIdKey(Listener::$accessTokenParamKey)
                ->setRequest($c['request']);

            return $resourceServer;
        };

        $this->container['storage.access_token'] = function () {
            return new AccessTokenStorage($this->app);
        };

        $this->container['storage.auth_code'] = function () {
            return new AuthCodeStorage($this->app);
        };

        $this->container['storage.client'] = function () {
            return new ClientStorage($this->app);
        };

        $this->container['storage.refresh_token'] = function () {
            return new RefreshTokenStorage($this->app);
        };

        $this->container['storage.scope'] = function () {
            return new ScopeStorage($this->app);
        };

        $this->container['storage.session'] = function () {
            return new SessionStorage($this->app);
        };
    }

    /**
     * @param string|null $key
     * @return Container|mixed
     */
    public function container($key = null)
    {
        return $key === null ? $this->container : $this->container[$key];
    }

    /**
     * @return int
     */
    public function getOptionAccessTokenTTL()
    {
        return $this->app->options()->bdApi_tokenTTL;
    }

    /**
     * @return int
     */
    public function getOptionAuthCodeTTL()
    {
        return $this->app->options()->bdApi_authCodeTTL;
    }

    /**
     * @return int
     */
    public function getOptionRefreshTokenTTL()
    {
        return $this->app->options()->bdApi_refreshTokenTTLDays * 86400;
    }

    /**
     * @return string[]
     */
    public function getScopeDefaults()
    {
        $scopes = [];
        $scopes[] = Server::SCOPE_READ;
        $scopes[] = Server::SCOPE_POST;
        $scopes[] = Server::SCOPE_MANAGE_ACCOUNT_SETTINGS;
        $scopes[] = Server::SCOPE_PARTICIPATE_IN_CONVERSATIONS;

        return $scopes;
    }

    /**
     * @param string $scopeId
     * @return null|\XF\Phrase
     */
    public function getScopeDescription($scopeId)
    {
        switch ($scopeId) {
            case self::SCOPE_READ:
            case self::SCOPE_POST:
            case self::SCOPE_MANAGE_ACCOUNT_SETTINGS:
            case self::SCOPE_PARTICIPATE_IN_CONVERSATIONS:
            case self::SCOPE_MANAGE_SYSTEM:
                break;
            default:
                return null;
        }

        return \XF::phrase('bdapi_scope_' . $scopeId);
    }

    /**
     * @param array $scopes
     * @param AbstractServer $server
     * @return array
     */
    public function getScopeObjArrayFromStrArray($scopes, $server)
    {
        $result = [];
        if (!is_array($scopes)) {
            return $result;
        }

        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                continue;
            }

            $description = $this->getScopeDescription($scope);
            if ($description === null) {
                continue;
            }

            $result[$scope] = (new ScopeEntity($server))->hydrate([
                'id' => $scope,
                'description' => $description
            ]);
        }

        return $result;
    }

    /**
     * @param array $scopes
     * @return array
     */
    public function getScopeStrArrayFromObjArray($scopes)
    {
        $scopeIds = [];
        if (!is_array($scopes)) {
            return $scopeIds;
        }

        /** @var ScopeEntity $scope */
        foreach ($scopes as $scope) {
            $scopeIds[] = $scope->getId();
        }

        return $scopeIds;
    }

    /**
     * @param Account $controller
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     * @throws \League\OAuth2\Server\Exception\InvalidGrantException
     */
    public function grantAuthCodeCheckParams($controller)
    {
        /** @var AuthorizationServer $authorizationServer */
        $authorizationServer = $this->container['server.auth'];

        /** @var AuthCodeGrant $authCodeGrant */
        $authCodeGrant = $authorizationServer->getGrantType('authorization_code');

        try {
            $params = $authCodeGrant->checkAuthorizeParams();

            if (isset($params['client'])) {
                /** @var ClientHybrid $client */
                $client = $params['client'];
                $params['client'] = $client->getXfClient();
            }

            if (isset($params['scopes'])) {
                $scopes = $params['scopes'];
                $params['scopes'] = $this->getScopeStrArrayFromObjArray($scopes);
            }

            return $params;
        } catch (\League\OAuth2\Server\Exception\OAuthException $e) {
            throw $this->buildControllerException($controller, $e);
        }
    }

    /**
     * @param Account $controller
     * @param array $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws \League\OAuth2\Server\Exception\InvalidGrantException
     * @throws \League\OAuth2\Server\Exception\UnsupportedResponseTypeException
     */
    public function grantAuthCodeNewAuthRequest($controller, array $params)
    {
        /** @var AuthorizationServer $authorizationServer */
        $authorizationServer = $this->container['server.auth'];

        $userId = $this->app->session()->get(SessionStorage::SESSION_KEY_USER_ID);
        $responseType = isset($params['response_type']) ? $params['response_type'] : 'code';
        switch ($responseType) {
            case 'code':
                /** @var AuthCodeGrant $authCodeGrant */
                $authCodeGrant = $authorizationServer->getGrantType('authorization_code');
                $authCodeType = SessionStorage::OWNER_TYPE_USER;
                $authCodeTypeId = $userId;
                $authCodeParams = $params;

                if (isset($authCodeParams['client'])) {
                    /** @var Client $xfClient */
                    $xfClient = $authCodeParams['client'];
                    $authCodeParams['client'] = $authorizationServer->getClientStorage()->get($xfClient->client_id);
                }

                if (isset($authCodeParams['scopes'])) {
                    $scopes = $authCodeParams['scopes'];
                    $authCodeParams['scopes'] = $this->getScopeObjArrayFromStrArray($scopes, $authorizationServer);
                }

                $redirectUri = $authCodeGrant->newAuthorizeRequest($authCodeType, $authCodeTypeId, $authCodeParams);
                break;
            case 'token':
                $accessToken = $this->newAccessToken($userId, $params['client'], $params['scopes']);
                /** @var ImplicitGrant $implicitGrant */
                $implicitGrant = $authorizationServer->getGrantType('implicit');
                $redirectUri = $implicitGrant->authorize($accessToken, $params);
                break;
            default:
                throw new \League\OAuth2\Server\Exception\UnsupportedResponseTypeException(
                    $responseType,
                    $params['redirect_uri']
                );
        }

        return $controller->redirect($redirectUri);
    }

    /**
     * @param OAuth2 $controller
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     * @throws \League\OAuth2\Server\Exception\InvalidGrantException
     * @throws \XF\PrintableException
     */
    public function grantFinalize($controller)
    {
        /** @var AuthorizationServer $authorizationServer */
        $authorizationServer = $this->container['server.auth'];

        $request = $authorizationServer->getRequest()->request;
        $clientId = $request->get('client_id');
        $password = $request->get('password');
        $passwordAlgo = $request->get('password_algo');
        if (!empty($clientId) && !empty($password) && !empty($passwordAlgo)) {
            /** @var Client|null $client */
            $client = $this->app->find('Xfrocks\Api:Client', $clientId);
            if ($client !== null) {
                $decryptedPassword = Crypt::decrypt($password, $passwordAlgo, $client->client_secret);
                if (!empty($decryptedPassword)) {
                    $request->set('client_secret', $client->client_secret);
                    $request->set('password', $decryptedPassword);
                    $request->set('password_algo', '');
                }
            }
        }

        $grantType = $request->get('grant_type');
        if ($grantType === 'password') {
            $scope = $request->get('scope');
            if (empty($scope)) {
                $scopeDefaults = implode(' ', $this->getScopeDefaults());
                $request->set('scope', $scopeDefaults);
            }
        }

        /** @var PasswordGrant $passwordGrant */
        $passwordGrant = $authorizationServer->getGrantType('password');
        $passwordGrant->setVerifyCredentialsCallback(function ($username, $password) use ($controller) {
            return $controller->verifyCredentials($username, $password);
        });

        $db = $controller->app()->db();
        $db->beginTransaction();
        try {
            $data = $authorizationServer->issueAccessToken();

            $db->commit();

            return $data;
        } catch (\League\OAuth2\Server\Exception\OAuthException $e) {
            $db->rollback();

            throw $this->buildControllerException($controller, $e);
        }
    }

    /**
     * @param string $userId
     * @param Client $client
     * @param string[] $scopes
     * @return AccessTokenEntity
     */
    public function newAccessToken($userId, $client, array $scopes)
    {
        /** @var AuthorizationServer $authorizationServer */
        $authorizationServer = $this->container['server.auth'];

        // Create a new session
        $session = new SessionEntity($authorizationServer);
        $session->setOwner(SessionStorage::OWNER_TYPE_USER, $userId);

        /** @var \League\OAuth2\Server\Entity\ClientEntity $libClient */
        $libClient = $authorizationServer->getClientStorage()->get($client->client_id);
        $session->associateClient($libClient);

        // Generate the access token
        $accessToken = new AccessTokenEntity($authorizationServer);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getOptionAccessTokenTTL() + time());

        $libScopes = $this->getScopeObjArrayFromStrArray($scopes, $authorizationServer);
        foreach ($libScopes as $libScope) {
            $session->associateScope($libScope);
            $accessToken->associateScope($libScope);
        }

        $session->save();
        $accessToken->setSession($session);
        $accessToken->save();

        return $accessToken;
    }

    /**
     * @return AccessTokenHybrid|null
     */
    public function parseRequest()
    {
        if ($this->parsedRequest) {
            throw new \RuntimeException('Cannot parse request twice');
        }

        /** @var ResourceServer $resourceServer */
        $resourceServer = $this->container['server.resource'];
        $accessDenied = false;
        try {
            $resourceServer->isValidRequest(false);
        } catch (\League\OAuth2\Server\Exception\AccessDeniedException $ade) {
            $accessDenied = true;
        } catch (\League\OAuth2\Server\Exception\OAuthException $e) {
            // ignore other exception
        }

        $this->parsedRequest = true;

        if ($accessDenied) {
            return null;
        }

        /** @var AccessTokenHybrid|null $accessTokenHybrid */
        $accessTokenHybrid = $resourceServer->getAccessToken();

        return $accessTokenHybrid;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setRequestQuery($key, $value)
    {
        $this->app->request()->set($key, $value);

        /** @var Request $request */
        $request = $this->container['request'];
        $request->query->set($key, $value);
    }

    /**
     * @param Controller $controller
     * @param \League\OAuth2\Server\Exception\OAuthException $e
     * @return \XF\Mvc\Reply\Exception
     */
    protected function buildControllerException($controller, $e)
    {
        $errors = [];
        if (!empty($e->errorType)) {
            switch ($e->errorType) {
                case 'access_denied':
                case 'invalid_client':
                case 'invalid_grant':
                case 'invalid_scope':
                case 'unauthorized_client':
                case 'unsupported_grant_type':
                case 'unsupported_response_type':
                    $errors[] = \XF::phrase('bdapi_oauth2_error_' . $e->errorType);
                    break;
                case 'invalid_credentials':
                    $errors[] = \XF::phrase('incorrect_password');
                    break;
                case 'server_error':
                    $errors[] = \XF::phrase('server_error_occurred');
                    break;
            }
        }
        if (count($errors) === 0) {
            $errors[] = $e->getMessage();
        }

        if ($e->httpStatusCode >= 500) {
            \XF::logException($e, false, 'API:', true);
        }

        if ($e->shouldRedirect()) {
            return $controller->exception($controller->redirect($e->getRedirectUri()));
        }

        // TODO: include $e->getHttpHeaders() data

        return $controller->errorException($errors, $e->httpStatusCode);
    }
}

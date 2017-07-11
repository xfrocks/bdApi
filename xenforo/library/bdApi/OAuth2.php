<?php

require(dirname(__FILE__) . '/Lib/oauth2-server-php/src/OAuth2/Autoloader.php');
OAuth2\Autoloader::register();

class bdApi_OAuth2 extends \OAuth2\Server
{
    /**
     * @var bdApi_Model_OAuth2
     */
    protected $_model;

    protected $_actionOauthToken_tfaProviders = array();

    /**
     * Process /oauth/token request.
     *
     * @param bdApi_ControllerApi_Abstract $controller
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionOauthToken(bdApi_ControllerApi_Abstract $controller)
    {
        foreach ($this->storages as $storage) {
            if ($storage instanceof bdApi_OAuth2_Storage) {
                $storage->setControllerApi($controller);
                break;
            }
        }

        $request = $this->_generateOAuth2Request();
        /** @var OAuth2\Response $response */
        $response = $this->handleTokenRequest($request);

        if ($response->isClientError()
            && count($this->_actionOauthToken_tfaProviders) > 0
        ) {
            // supports XenForo 1.5+ two factor authentication
            $response->setStatusCode(202);

            $errorDescription = $response->getParameter('error_description');
            if (!empty($errorDescription)) {
                $response->setParameter('error_description', 'Two-factor authorization code is required');
            }

            $response->addHttpHeaders(array(
                'X-Api-Tfa-Providers' => implode(', ', array_keys($this->_actionOauthToken_tfaProviders)),
            ));
        }

        return $this->_generateControllerResponse($controller, $response);
    }

    public function actionOauthToken_setTfaProviders(array $providers)
    {
        // supports XenForo 1.5+ two factor authentication
        $this->_actionOauthToken_tfaProviders = $providers;
    }

    /**
     * Process /oauth/authorize request (step 1).
     *
     * @param XenForo_Controller $controller
     * @param array $authorizeParams
     *
     * @return bool|XenForo_ControllerResponse_Abstract
     */
    public function actionOauthAuthorize1(XenForo_Controller $controller, array $authorizeParams)
    {
        if (!empty($authorizeParams['redirect_uri'])) {
            $storage = $this->storages['client'];
            if ($storage instanceof bdApi_OAuth2_Storage) {
                $storage->setRequestRedirectUri($authorizeParams['redirect_uri']);
            }
        }

        $request = new OAuth2\Request($authorizeParams);
        $validated = $this->validateAuthorizeRequest($request);

        if (!$validated) {
            return $this->_generateControllerResponse($controller, $this->getResponse());
        }

        return true;
    }

    /**
     * Process /oauth/authorize request (step 2).
     *
     * @param XenForo_Controller $controller
     * @param array $authorizeParams
     * @param $accepted
     * @param $userId
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionOauthAuthorize2(XenForo_Controller $controller, array $authorizeParams, $accepted, $userId)
    {
        if (!empty($authorizeParams['redirect_uri'])) {
            $storage = $this->getStorage('client');
            if ($storage instanceof bdApi_OAuth2_Storage) {
                $storage->setRequestRedirectUri($authorizeParams['redirect_uri']);
            }
        }

        $request = new OAuth2\Request($authorizeParams);
        $response = new OAuth2\Response();

        $this->handleAuthorizeRequest($request, $response, $accepted, $userId);

        return $this->_generateControllerResponse($controller, $response);
    }

    /**
     * Get effective token of current request.
     *
     * @return array
     */
    public function getEffectiveToken()
    {
        static $effectiveToken = null;

        if ($effectiveToken === null) {
            $effectiveToken = array();

            $request = $this->_generateOAuth2Request();
            if ($this->verifyResourceRequest($request)) {
                $token = $this->getResourceController()->getToken();
                $effectiveToken = $token;
            }
        }

        return $effectiveToken;
    }

    /**
     * Create access token for specified client/user pair.
     *
     * @param string $clientId
     * @param int $userId
     * @param string $scope
     * @param int|null $ttl
     * @param bool $includeRefreshToken
     *
     * @return array
     */
    public function createAccessToken($clientId, $userId, $scope = null, $ttl = null, $includeRefreshToken = true)
    {
        $token = $this->getAccessTokenResponseType()->createAccessToken(
            $clientId,
            $userId,
            $scope,
            $includeRefreshToken
        );

        if ($ttl !== null) {
            $dbToken = $this->_model->getTokenModel()->getTokenByText($token['access_token']);
            if (!empty($dbToken)) {
                /** @var bdApi_DataWriter_Token $dw */
                $dw = XenForo_DataWriter::create('bdApi_DataWriter_Token');
                $dw->setExistingData($dbToken);
                $dw->set('expire_date', time() + $ttl);
                $dw->save();

                $token['expires_in'] = $ttl;
            }
        }

        return $token;
    }

    /**
     * Get the expected audience value for JWT Bearer grant type.
     *
     * @return string
     */
    public function getJwtAudience()
    {
        $aud = bdApi_Data_Helper_Core::safeBuildApiLink('full:index', null, array('oauth_token' => ''));

        $indexDotPhp = 'index.php';
        if (substr($aud, -strlen($indexDotPhp)) === $indexDotPhp) {
            $aud = substr($aud, 0, -strlen($indexDotPhp));
        }

        $aud = rtrim($aud, '/');

        return $aud;
    }

    /**
     * Get XenForo controller response for default OAuth2 response.
     *
     * @param XenForo_Controller $controller
     *
     * @return XenForo_ControllerResponse_Abstract
     */
    public function getErrorControllerResponse(XenForo_Controller $controller)
    {
        /** @var OAuth2\Response $response */
        $response = $this->response;

        if (!empty($response) && $response->getParameter('error')) {
            return $this->_generateControllerResponse($controller, $response);
        }

        return null;
    }

    /**
     * Constructor
     *
     * @param bdApi_Model_OAuth2 $model
     */
    public function __construct(bdApi_Model_OAuth2 $model)
    {
        $storage = new bdApi_OAuth2_Storage($model, $this);

        parent::__construct(array(
            'access_token' => $storage,
            'authorization_code' => $storage,
            'client_credentials' => $storage,
            'user_credentials' => $storage,
            'refresh_token' => $storage,
        ), array(
            'auth_code_lifetime' => bdApi_Option::get('authCodeTTL'),
            'access_lifetime' => bdApi_Option::get('tokenTTL'),
            'refresh_token_lifetime' => bdApi_Option::get('refreshTokenTTLDays') * 86400,
            'token_param_name' => 'oauth_token',
            'enforce_state' => false,
            'require_exact_redirect_uri' => false,
            'allow_implicit' => true,
            'always_issue_new_refresh_token' => true,
        ));

        $this->_model = $model;
    }

    protected function createDefaultAccessTokenResponseType()
    {
        $config = array_intersect_key(
            $this->config,
            array_flip(explode(' ', 'access_lifetime refresh_token_lifetime'))
        );
        $config['token_type'] = $this->tokenType ? $this->tokenType->getTokenType() : $this->getDefaultTokenType()->getTokenType();

        return new bdApi_OAuth2_ResponseType_AccessToken(
            $this->storages['access_token'],
            $this->storages['access_token'],
            $config
        );
    }

    protected function getDefaultGrantTypes()
    {
        $grantTypes = parent::getDefaultGrantTypes();

        $aud = $this->getJwtAudience();
        if (!empty($aud)) {
            $jwtBearer = new bdApi_OAuth2_GrantType_JwtBearer(reset($this->storages), $aud);
            $grantTypes[$jwtBearer->getQuerystringIdentifier()] = $jwtBearer;
        }

        return $grantTypes;
    }

    protected function _generateOAuth2Request()
    {
        $server = $_SERVER;
        if (isset($server['CONTENT_TYPE'])) {
            // workaround to accept multi-part request
            unset($server['CONTENT_TYPE']);
        }

        return new OAuth2\Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $server);
    }

    protected function _generateControllerResponse(XenForo_Controller $controller, OAuth2\Response $response)
    {
        if ($response->isRedirection()) {
            return $controller->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                $response->getHttpHeader('Location')
            );
        }

        $params = $response->getParameters();
        $params['_statusCode'] = $response->getStatusCode();
        $params['_headers'] = $response->getHttpHeaders();

        if ($controller instanceof bdApi_ControllerApi_Abstract) {
            return $controller->responseData('bdApi_ViewApi_OAuth', $params);
        } else {
            if ($response->isClientError()) {
                return $controller->responseError(
                    $response->getParameter('error_description'),
                    $response->getStatusCode()
                );
            } else {
                $controller->getRouteMatch()->setResponseType('json');
                return $controller->responseView('bdApi_ViewPublic_OAuth', '', $params);
            }
        }
    }
}

class bdApi_OAuth2_Storage implements
    OAuth2\Storage\AccessTokenInterface,
    OAuth2\Storage\AuthorizationCodeInterface,
    OAuth2\Storage\ClientInterface,
    OAuth2\Storage\ClientCredentialsInterface,
    OAuth2\Storage\JwtBearerInterface,
    OAuth2\Storage\RefreshTokenInterface,
    OAuth2\Storage\ScopeInterface,
    OAuth2\Storage\UserCredentialsInterface
{

    /** @var bdApi_Model_OAuth2 */
    protected $_model;

    /** @var bdApi_OAuth2 */
    protected $_server;

    protected $_requestRedirectUri = '';

    /** @var bdApi_ControllerApi_Abstract */
    protected $_controller = null;

    public function getModel()
    {
        return $this->_model;
    }

    public function setRequestRedirectUri($redirectUri)
    {
        $this->_requestRedirectUri = $redirectUri;
    }

    public function setControllerApi(bdApi_ControllerApi_Abstract $controller)
    {
        $this->_controller = $controller;
    }

    public function __construct(bdApi_Model_OAuth2 $model, bdApi_OAuth2 $server)
    {
        $this->_model = $model;
        $this->_server = $server;
    }

    public function getAccessToken($oauthToken)
    {
        $token = $this->_model->getTokenModel()->getTokenByText($oauthToken);

        if (empty($token)) {
            // token not found
            return null;
        }

        return array_merge($token, array(
            'expires' => $token['expire_date'],
        ));
    }

    public function setAccessToken($oauthToken, $clientId, $userId, $expires, $scope = null)
    {
        /* @var $dw bdApi_DataWriter_Token */
        $dw = XenForo_DataWriter::create('bdApi_DataWriter_Token');

        $dw->set('token_text', $oauthToken);
        $dw->set('client_id', $clientId);
        $dw->set('user_id', $userId);
        $dw->set('expire_date', $expires);
        $dw->set('scope', $scope ? $scope : '');

        $dw->save();
    }

    public function getAuthorizationCode($code)
    {
        $authCode = $this->_model->getAuthCodeModel()->getAuthCodeByText($code);

        if (empty($authCode)) {
            // auth code not found
            return null;
        }

        return array_merge($authCode, array(
            'expires' => $authCode['expire_date'],
        ));
    }

    public function setAuthorizationCode($code, $clientId, $userId, $redirectUri, $expires, $scope = null)
    {
        /* @var $dw bdApi_DataWriter_AuthCode */
        $dw = XenForo_DataWriter::create('bdApi_DataWriter_AuthCode');

        $dw->set('auth_code_text', $code);
        $dw->set('client_id', $clientId);
        $dw->set('user_id', $userId);
        $dw->set('redirect_uri', urldecode($redirectUri));
        $dw->set('expire_date', $expires);
        $dw->set('scope', $scope ? $scope : '');

        $dw->save();
    }

    public function expireAuthorizationCode($code)
    {
        $authCode = $this->_model->getAuthCodeModel()->getAuthCodeByText($code);

        if (!empty($authCode)) {
            /* @var $dw bdApi_DataWriter_AuthCode */
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_AuthCode');
            $dw->setExistingData($authCode, true);
            $dw->delete();
        }
    }

    public function getClientDetails($clientId)
    {
        $client = $this->_model->getClientModel()->getClientById($clientId);

        if (empty($client)) {
            // client not found
            return false;
        }

        if (!empty($this->_requestRedirectUri)) {
            $clientRedirectUri = $this->_model->getClientModel()->getWhitelistedRedirectUri(
                $client,
                $this->_requestRedirectUri
            );
            if (is_string($clientRedirectUri)) {
                $client['redirect_uri'] .= sprintf(' %s', $clientRedirectUri);
            }
        }

        return array_merge($client, array(
            'user_id' => 0,
            'scope' => bdApi_Template_Helper_Core::getInstance()->scopeJoin($this->_model->getSystemSupportedScopes()),
        ));
    }

    public function getClientScope($clientId)
    {
        return bdApi_Template_Helper_Core::getInstance()->scopeJoin($this->_model->getSystemSupportedScopes());
    }

    public function checkRestrictedGrantType($clientId, $grant_type)
    {
        return true;
    }

    public function checkClientCredentials($clientId, $clientSecret = null)
    {
        $client = $this->_model->getClientModel()->getClientById($clientId);

        if (empty($client)) {
            // client not found
            return false;
        }

        if (!$this->_model->getClientModel()->verifySecret($client, $clientSecret)) {
            // the secret exists but not valid
            return false;
        }

        return true;
    }

    public function isPublicClient($client_id)
    {
        // TODO: Implement isPublicClient() method.
        return false;
    }

    public function getClientKey($clientId, $subject)
    {
        $client = $this->_model->getClientModel()->getClientById($clientId);

        if (empty($client)) {
            // client not found
            return false;
        }

        if (empty($client['options']['public_key'])) {
            // no public key has been configured
            return false;
        }

        return $client['options']['public_key'];
    }

    public function getJti($client_id, $subject, $audience, $expiration, $jti)
    {
        return null;
    }

    public function setJti($client_id, $subject, $audience, $expiration, $jti)
    {
        // do nothing
    }

    public function getRefreshToken($refreshToken)
    {
        $token = $this->_model->getRefreshTokenModel()->getRefreshTokenByText($refreshToken);

        if (empty($token)) {
            // refresh token not found
            return null;
        }

        return array_merge($token, array(
            'refresh_token' => $token['refresh_token_text'],
            'expires' => $token['expire_date'],
        ));
    }

    public function setRefreshToken($refreshToken, $clientId, $userId, $expires, $scope = null)
    {
        /* @var $dw bdApi_DataWriter_RefreshToken */
        $dw = XenForo_DataWriter::create('bdApi_DataWriter_RefreshToken');

        $dw->set('refresh_token_text', $refreshToken);
        $dw->set('client_id', $clientId);
        $dw->set('user_id', $userId);
        $dw->set('expire_date', $expires);
        $dw->set('scope', $scope ? $scope : '');

        $dw->save();
    }

    public function unsetRefreshToken($refreshToken)
    {
        $token = $this->_model->getRefreshTokenModel()->getRefreshTokenByText($refreshToken);

        if (!empty($token)) {
            /* @var $dw bdApi_DataWriter_RefreshToken */
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_RefreshToken');
            $dw->setExistingData($token, true);
            $dw->delete();
        }
    }

    public function scopeExists($scope)
    {
        return in_array($scope, $this->_model->getSystemSupportedScopes(), true);
    }

    public function getDefaultScope($client_id = null)
    {
        return false;
    }

    public function checkUserCredentials($nameOrEmail, $password)
    {
        /** @var XenForo_Model_Login $loginModel */
        $loginModel = $this->_model->getModelFromCache('XenForo_Model_Login');
        if ($loginModel->requireLoginCaptcha($nameOrEmail)) {
            return false;
        }

        $userId = $this->_model->getUserModel()->validateAuthentication($nameOrEmail, $password);

        if (!$userId) {
            $loginModel->logLoginAttempt($nameOrEmail);
        }

        if (!$this->_checkUserCredentials_runTfaValidation($userId)) {
            return false;
        }

        if (!empty($userId)) {
            $loginModel->clearLoginAttempts($nameOrEmail);

            return true;
        } else {
            return false;
        }
    }

    protected function _checkUserCredentials_runTfaValidation($userId)
    {
        if ($userId < 1
            || XenForo_Application::$versionId < 1050000
        ) {
            return true;
        }

        if ($this->_controller === null) {
            // since XenForo 1.5+, $_controller must be set to check for two factor authentication
            // otherwise, deny access immediately
            return false;
        }

        /** @var XenForo_ControllerHelper_Login $loginHelper */
        $loginHelper = $this->_controller->getHelper('Login');
        $user = $this->_model->getUserModel()->getFullUserById($userId);

        if (!$loginHelper->userTfaConfirmationRequired($user)) {
            return true;
        }

        /** @var XenForo_Model_Tfa $tfaModel */
        $tfaModel = $this->_model->getModelFromCache('XenForo_Model_Tfa');
        $providers = $tfaModel->getTfaConfigurationForUser($user['user_id'], $userData);
        if (empty($providers)) {
            return true;
        }
        $this->_server->actionOauthToken_setTfaProviders($providers);

        $tfaProvider = $this->_controller->getInput()->filterSingle('tfa_provider', XenForo_Input::STRING);
        if (strlen($tfaProvider) === 0) {
            return false;
        }

        $tfaTrigger = $this->_controller->getInput()->filterSingle('tfa_trigger', XenForo_Input::BOOLEAN);
        if ($tfaTrigger) {
            $loginHelper->triggerTfaCheck($user, $tfaProvider, $providers, $userData);
            throw $this->_controller->responseException($this->_controller->responseMessage(
                new XenForo_Phrase('changes_saved')
            ));
        }

        $loginHelper->assertNotTfaAttemptLimited($user['user_id']);
        if ($loginHelper->runTfaValidation($user, $tfaProvider, $providers, $userData) === true) {
            return true;
        }

        throw $this->_controller->responseException($this->_controller->responseError(
            new XenForo_Phrase('two_step_verification_value_could_not_be_confirmed')
        ));
    }

    public function getUserDetails($nameOrEmail)
    {
        $user = $this->_model->getUserModel()->getUserByNameOrEmail($nameOrEmail);

        if (empty($user)) {
            // user not found
            return false;
        }

        return array(
            'user_id' => $user['user_id'],
            'scope' => bdApi_Template_Helper_Core::getInstance()->scopeJoin($this->_model->getSystemSupportedScopes()),
        );
    }
}

class bdApi_OAuth2_GrantType_JwtBearer extends OAuth2\GrantType\JwtBearer
{
    public function getScope()
    {
        $storage = $this->storage;
        if ($storage instanceof bdApi_OAuth2_Storage) {
            if ($this->getUserId() > 0) {
                return $storage->getModel()->getAutoAndUserScopes($this->getClientId(), $this->getUserId());
            } else {
                return bdApi_Template_Helper_Core::getInstance()->scopeJoin($storage->getModel()->getSystemSupportedScopes());
            }
        }

        return '';
    }
}

class bdApi_OAuth2_ResponseType_AccessToken extends OAuth2\ResponseType\AccessToken
{
    public function createAccessToken($clientId, $userId, $scope = null, $includeRefreshToken = true)
    {
        $token = parent::createAccessToken($clientId, $userId, $scope, $includeRefreshToken);
        $token['user_id'] = $userId;

        if (!empty($token['refresh_token'])
            && !empty($this->config['refresh_token_lifetime'])
        ) {
            $token['refresh_token_expires_in'] = $this->config['refresh_token_lifetime'];
        }

        return $token;
    }
}

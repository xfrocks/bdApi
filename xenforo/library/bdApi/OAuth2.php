<?php

require(dirname(__FILE__) . '/Lib/oauth2-server-php/src/OAuth2/Autoloader.php');
OAuth2\Autoloader::register();

class bdApi_OAuth2 extends \OAuth2\Server
{
    /**
     * @var bdApi_Model_OAuth2
     */
    protected $_model;

    public function actionOauthToken(bdApi_ControllerApi_Abstract $controller)
    {
        $response = $this->handleTokenRequest(OAuth2\Request::createFromGlobals());

        return $this->_generateControllerResponse($controller, $response);
    }

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

    public function actionOauthAuthorize2(XenForo_Controller $controller, array $authorizeParams, $accepted, $userId)
    {
        if (!empty($authorizeParams['redirect_uri'])) {
            $storage = $this->storages['client'];
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

            if ($this->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
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
     *
     * @return array
     */
    public function createAccessToken($clientId, $userId, $scope = null, $ttl = null)
    {
        $token = $this->getAccessTokenResponseType()->createAccessToken($clientId, $userId, $scope);

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
     * Constructor
     *
     * @param bdApi_Model_OAuth2 $model
     */
    public function __construct(bdApi_Model_OAuth2 $model)
    {
        $storage = new bdApi_OAuth2_Storage($model);

        parent::__construct($storage, array(
            'auth_code_lifetime' => bdApi_Option::get('authCodeTTL'),
            'access_lifetime' => bdApi_Option::get('tokenTTL'),
            'refresh_token_lifetime' => bdApi_Option::get('refreshTokenTTLDays') * 86400,
            'www_realm' => $model->getSystemAuthenticationRealm(),
            'token_param_name' => 'oauth_token',
            'enforce_state' => false,
            'require_exact_redirect_uri' => false,
            'allow_implicit' => true,
        ));

        $this->addGrantType(new \OAuth2\GrantType\AuthorizationCode($storage));
        $this->addGrantType(new \OAuth2\GrantType\UserCredentials($storage));
        $this->addGrantType(new \OAuth2\GrantType\RefreshToken($storage));

        $this->_model = $model;
    }

    protected function _generateControllerResponse(XenForo_Controller $controller, OAuth2\Response $response)
    {
        if ($response->isRedirection()) {
            return $controller->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $response->getHttpHeader('Location'));
        }

        if ($controller instanceof bdApi_ControllerApi_Abstract) {
            if ($response->isClientError()) {
                return $controller->responseData('bdApi_ViewApi_OAuth_Error', $response->getParameters());
            } else {
                return $controller->responseData('bdApi_ViewApi_OAuth_Success', $response->getParameters());
            }
        } else {
            if ($response->isClientError()) {
                return $controller->responseError($response->getParameter('error_description'), $response->getStatusCode());
            } else {
                $controller->getRouteMatch()->setResponseType('json');
                return $controller->responseView('bdApi_ViewApi_OAuth_Success', '', $response->getParameters());
            }
        }
    }
}

class bdApi_OAuth2_Storage implements
    OAuth2\Storage\AccessTokenInterface,
    OAuth2\Storage\AuthorizationCodeInterface,
    OAuth2\Storage\ClientInterface,
    OAuth2\Storage\ClientCredentialsInterface,
    OAuth2\Storage\RefreshTokenInterface,
    OAuth2\Storage\ScopeInterface,
    OAuth2\Storage\UserCredentialsInterface
{

    /** @var bdApi_Model_OAuth2 */
    protected $_model;

    protected $_requestRedirectUri = '';

    public function setRequestRedirectUri($redirectUri)
    {
        $this->_requestRedirectUri = $redirectUri;
    }

    public function __construct(bdApi_Model_OAuth2 $model)
    {
        $this->_model = $model;
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
            $clientRedirectUri = $this->_model->getClientModel()->getWhitelistedRedirectUri($client, $this->_requestRedirectUri);
            if (is_string($clientRedirectUri)) {
                $client['redirect_uri'] .= sprintf(' %s', $clientRedirectUri);
            }
        }

        return $client;
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

    public function checkUserCredentials($username, $password)
    {
        $userId = $this->_model->getUserModel()->validateAuthentication($username, $password);

        if (!empty($userId)) {
            return true;
        } else {
            return false;
        }
    }

    public function getUserDetails($username)
    {
        $user = $this->_model->getUserModel()->getUserByName($username);

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

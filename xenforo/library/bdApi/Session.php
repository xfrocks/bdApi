<?php

class bdApi_Session extends XenForo_Session
{
    /**
     * The effective OAuth token of current request.
     *
     * @var array|false
     */
    protected $_oauthToken = false;

    /**
     * The effective OAuth client of current request.
     *
     * @var array|false
     */
    protected $_oauthClient = false;

    public function getOAuthClientId()
    {
        if (!empty($this->_oauthToken)) {
            return $this->_oauthToken['client_id'];
        }

        return '';
    }

    public function getOAuthClientSecret()
    {
        if (!empty($this->_oauthToken)) {
            if (empty($this->_oauthClient)) {
                $this->_oauthClient = self::_bdApi_getClientModel()->getClientById($this->_oauthToken['client_id']);
            }

            return $this->_oauthClient['client_secret'];
        }

        return false;
    }

    /**
     * Gets the effective OAuth token text of current request or false
     * if no token could be found.
     */
    public function getOAuthTokenText()
    {
        if (!empty($this->_oauthToken)) {
            return $this->_oauthToken['token_text'];
        }

        return false;
    }

    public function isValidRedirectUri($uri)
    {
        $this->getOAuthClientSecret();

        $clientRedirectUri = false;

        if (!empty($this->_oauthClient['client_id'])) {
            /** @var bdApi_Model_Client $clientModel */
            $clientModel = XenForo_Model::create('bdApi_Model_Client');
            $clientRedirectUri = $clientModel->getWhitelistedRedirectUri($this->_oauthClient, $uri);

            if ($clientRedirectUri === false && !empty($this->_oauthClient['redirect_uri'])) {
                $clientRedirectUri = $this->_oauthClient['redirect_uri'];
            }
        }

        if ($clientRedirectUri !== false) {
            $uri = rtrim($uri, '/');
            $clientUri = rtrim($clientRedirectUri, '/');

            if (strpos($uri, $clientUri) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks for the specified scope to see if the effective scopes
     * contain it.
     *
     * @param string $scope
     *
     * @return boolean true if the scope is found
     */
    public function checkScope($scope)
    {
        $scopes = $this->get('scopes');
        if (empty($scopes)) {
            // no scopes...
            return false;
        }

        return in_array($scope, $scopes);
    }

    /**
     * Starts running the API session handler. This will automatically log in the
     * user via OAuth if needed, and setup the visitor object. The session will be
     * registered in the registry.
     *
     * @param Zend_Controller_Request_Http|null $request
     *
     * @return XenForo_Session
     */
    public static function startApiSession(Zend_Controller_Request_Http $request = null)
    {
        if (!$request) {
            $request = new Zend_Controller_Request_Http();
        }

        if (XenForo_Application::$versionId >= 1020000) {
            $addOns = XenForo_Application::get('addOns');
            if (empty($addOns['bdApi'])) {
                die('The API is currently disabled.');
            }
        }

        $session = new bdApi_Session();
        $session->start();

        XenForo_Application::set('session', $session);

        $options = $session->getAll();

        $visitor = XenForo_Visitor::setup($session->get('user_id'), $options);

        if (empty($visitor['user_id'])) {
            $guestUsername = $request->getParam('guestUsername');
            if (!empty($guestUsername)) {
                $visitor['username'] = $guestUsername;
            }
        }

        return $session;
    }

    public function start($sessionId = null, $ipAddress = null)
    {
        parent::start($sessionId, $ipAddress);

        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = XenForo_Model::create('bdApi_Model_OAuth2');

        $helper = bdApi_Template_Helper_Core::getInstance();

        $this->_oauthToken = $oauth2Model->getServer()->getEffectiveToken();

        if (empty($this->_oauthToken) AND isset($_REQUEST['oauth_token'])) {
            // added support for one time oauth token
            $parts = explode(',', $_REQUEST['oauth_token']);
            $userId = 0;
            $timestamp = 0;
            $once = '';
            $client = null;

            if (count($parts) == 4) {
                $userId = intval($parts[0]);
                $timestamp = intval($parts[1]);
                $once = $parts[2];

                if ($timestamp >= XenForo_Application::$time) {
                    $client = $oauth2Model->getClientModel()->getClientById($parts[3]);
                }
            }

            if (!empty($client)) {
                if ($userId == 0) {
                    // guest
                    if ($once == md5($userId . $timestamp . $client['client_secret'])) {
                        // make up fake token with full scopes for guest
                        $this->_oauthToken = array(
                            'token_id' => 0,
                            'client_id' => $client['client_id'],
                            'token_text' => '',
                            'expire_date' => XenForo_Application::$time,
                            'issue_date' => XenForo_Application::$time,
                            'user_id' => $userId,
                            'scope' => $helper->scopeJoin($oauth2Model->getSystemSupportedScopes()),
                        );
                    }
                } else {
                    // user
                    $userTokens = $oauth2Model->getTokenModel()->getTokens(array('user_id' => $userId));
                    foreach ($userTokens as $userToken) {
                        if ($userToken['expire_date'] >= XenForo_Application::$time) {
                            if ($once == md5($userId . $timestamp . $userToken['token_text'] . $client['client_secret'])) {
                                $this->_oauthToken = $userToken;
                            }
                        }
                    }
                }

                if (!empty($this->_oauthToken)) {
                    // oauth token is set using one time token
                    // update the token text to avoid exposing real access token
                    $this->_oauthToken['token_text'] = $_REQUEST['oauth_token'];
                }
            }
        }

        if (!empty($this->_oauthToken)) {
            if (!empty($this->_oauthToken['user_id'])) {
                $this->changeUserId($this->_oauthToken['user_id']);
            }

            $scopes = $helper->scopeSplit($this->_oauthToken['scope']);
            $this->set('scopes', $scopes);
        } else {
            $guestScopes = array();

            if (!bdApi_Option::get('restrictAccess')) {
                $guestScopes[] = bdApi_Model_OAuth2::SCOPE_READ;
            }

            $this->set('scopes', $guestScopes);
        }
    }

    public function fakeStart(array $client, XenForo_Visitor $visitor, array $scopes)
    {
        $this->_oauthToken = array(
            'token_id' => 0,
            'client_id' => $client['client_id'],
            'token_text' => '',
            'expire_date' => XenForo_Application::$time,
            'issue_date' => XenForo_Application::$time,
            'user_id' => $visitor['user_id'],
            'scope' => bdApi_Template_Helper_Core::getInstance()->scopeJoin($scopes),
        );
        $this->changeUserId($visitor['user_id']);
        $this->set('scopes', $scopes);

        XenForo_Application::set('_bdApi_session', $this);
    }

    public function getSessionFromSource($sessionId)
    {
        // api sessions are not saved
        // so it's unnecessary to query the db for it
        return false;
    }

    public function save()
    {
        // do nothing
    }

    public function saveSessionToSource($sessionId, $isUpdate)
    {
        // do nothing
    }

    public function delete()
    {
        // do nothing
    }

    public function deleteSessionFromSource($sessionId)
    {
        // do nothing
    }

    /**
     * @return bdApi_Model_Client
     */
    protected static function _bdApi_getClientModel()
    {
        static $model = null;

        if ($model === null) {
            $model = XenForo_Model::create('bdApi_Model_Client');
        }

        return $model;
    }
}

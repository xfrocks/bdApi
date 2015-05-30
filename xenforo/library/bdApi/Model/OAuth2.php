<?php

class bdApi_Model_OAuth2 extends XenForo_Model
{
    const SCOPE_READ = 'read';
    const SCOPE_POST = 'post';
    const SCOPE_MANAGE_ACCOUNT_SETTINGS = 'usercp';
    const SCOPE_PARTICIPATE_IN_CONVERSATIONS = 'conversate';
    const SCOPE_MANAGE_SYSTEM = 'admincp';

    protected static $_serverInstance = false;

    /**
     * Gets the server object. Only one instance will be created for
     * each page request.
     *
     * @return bdApi_OAuth2
     */
    public function getServer()
    {
        if (self::$_serverInstance === false) {
            self::$_serverInstance = new bdApi_OAuth2($this);
        }

        return self::$_serverInstance;
    }

    public function getAuthorizeParamsInputFilter()
    {
        return array(
            'client_id' => XenForo_Input::STRING,
            'response_type' => XenForo_Input::STRING,
            'redirect_uri' => XenForo_Input::STRING,
            'state' => XenForo_Input::STRING,
            'scope' => XenForo_Input::STRING,

            'social' => XenForo_Input::STRING,
        );
    }

    /**
     * Gets supported scopes for server. Other add-ons can override
     * this method to support more scopes.
     *
     * @return array an array of supported scopes
     */
    public function getSystemSupportedScopes()
    {
        return array(
            self::SCOPE_READ,
            self::SCOPE_POST,
            self::SCOPE_MANAGE_ACCOUNT_SETTINGS,
            self::SCOPE_PARTICIPATE_IN_CONVERSATIONS,
            self::SCOPE_MANAGE_SYSTEM,
        );
    }

    public function getAutoAndUserScopes($clientId, $userId)
    {
        $client = $this->getClientModel()->getClientById($clientId);
        if (empty($client)) {
            return '';
        }

        $scopes = array();
        if (!empty($client['options']['auto_authorize'])) {
            foreach ($client['options']['auto_authorize'] as $scope => $canAutoAuthorize) {
                if ($canAutoAuthorize) {
                    $scopes[] = $scope;
                }
            }
        }

        $userScopes = $this->getUserScopeModel()->getUserScopes($client['client_id'], $userId);
        if (!empty($userScopes)) {
            foreach ($userScopes as $scope => $userScope) {
                $scopes[] = $scope;
            }
            $scopes = array_unique($scopes);
        }

        return bdApi_Template_Helper_Core::getInstance()->scopeJoin($scopes);
    }

    /**
     * Generate key pair to use with JWT Bearer grant type.
     *
     * @return array of $privKey and $pubKey
     */
    public function generateKeyPair()
    {
        $key = openssl_pkey_new();

        openssl_pkey_export($key, $privKey);

        $details = openssl_pkey_get_details($key);
        $pubKey = $details["key"];

        return array($privKey, $pubKey);
    }

    /**
     * @return XenForo_Model_User
     */
    public function getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }

    /**
     * @return bdApi_Model_AuthCode
     */
    public function getAuthCodeModel()
    {
        return $this->getModelFromCache('bdApi_Model_AuthCode');
    }

    /**
     * @return bdApi_Model_Client
     */
    public function getClientModel()
    {
        return $this->getModelFromCache('bdApi_Model_Client');
    }

    /**
     * @return bdApi_Model_RefreshToken
     */
    public function getRefreshTokenModel()
    {
        return $this->getModelFromCache('bdApi_Model_RefreshToken');
    }

    /**
     * @return bdApi_Model_Token
     */
    public function getTokenModel()
    {
        return $this->getModelFromCache('bdApi_Model_Token');
    }

    /**
     * @return bdApi_Model_UserScope
     */
    public function getUserScopeModel()
    {
        return $this->getModelFromCache('bdApi_Model_UserScope');
    }

    /**
     * @return bdApi_Model_Subscription
     */
    public function getSubscriptionModel()
    {
        return $this->getModelFromCache('bdApi_Model_Subscription');
    }

    /**
     * @return bdApi_Model_Log
     */
    public function getLogModel()
    {
        return $this->getModelFromCache('bdApi_Model_Log');
    }


}

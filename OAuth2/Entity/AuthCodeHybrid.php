<?php

namespace Xfrocks\Api\OAuth2\Entity;

use League\OAuth2\Server\AbstractServer;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use Xfrocks\Api\Entity\AuthCode;

class AuthCodeHybrid extends AuthCodeEntity
{
    /**
     * @var AuthCode
     */
    protected $xfAuthCode;

    /**
     * @param AbstractServer $server
     * @param AuthCode $xfAuthCode
     */
    public function __construct($server, $xfAuthCode)
    {
        parent::__construct($server);

        $this->xfAuthCode = $xfAuthCode;
        $this->setId($xfAuthCode->auth_code_text);
        $this->setRedirectUri($xfAuthCode->redirect_uri);
        $this->setExpireTime($xfAuthCode->expire_date);
    }

    /**
     * @return AuthCode
     */
    public function getXfAuthCode()
    {
        return $this->xfAuthCode;
    }
}

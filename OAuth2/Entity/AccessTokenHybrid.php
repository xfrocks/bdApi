<?php

namespace Xfrocks\Api\OAuth2\Entity;

use League\OAuth2\Server\AbstractServer;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use Xfrocks\Api\Entity\Token;

class AccessTokenHybrid extends AccessTokenEntity
{
    /**
     * @var Token
     */
    protected $xfToken;

    /**
     * @param AbstractServer $server
     * @param Token $xfToken
     */
    public function __construct($server, $xfToken)
    {
        parent::__construct($server);

        $this->xfToken = $xfToken;
        $this->setId($xfToken->token_text);
        $this->setExpireTime($xfToken->expire_date);
    }

    /**
     * @return Token
     */
    public function getXfToken()
    {
        return $this->xfToken;
    }
}

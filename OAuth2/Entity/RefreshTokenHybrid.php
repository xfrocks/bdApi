<?php

namespace Xfrocks\Api\OAuth2\Entity;

use League\OAuth2\Server\AbstractServer;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use Xfrocks\Api\Entity\RefreshToken;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\OAuth2\Storage\AccessTokenStorage;

class RefreshTokenHybrid extends RefreshTokenEntity
{
    /**
     * @var RefreshToken
     */
    protected $xfRefreshToken;

    /**
     * @param AbstractServer $server
     * @param RefreshToken $xfRefreshToken
     */
    public function __construct($server, $xfRefreshToken)
    {
        parent::__construct($server);

        $this->xfRefreshToken = $xfRefreshToken;
        $this->setId($xfRefreshToken->refresh_token_text);
        $this->setExpireTime($xfRefreshToken->expire_date);

        /** @var AccessTokenStorage $accessTokenStorage */
        $accessTokenStorage = $server->getAccessTokenStorage();
        $fakeTokenText = $accessTokenStorage->generateFakeTokenText();

        $xfApp = $xfRefreshToken->app();
        /** @var Token $fakeAccessToken */
        $fakeAccessToken = $xfApp->em()->instantiateEntity('Xfrocks\Api:Token', [
            'token_id' => 0,
            'token_text' => $fakeTokenText,
            'client_id' => $xfRefreshToken->client_id,
            'user_id' => $xfRefreshToken->user_id,
            'scope' => $xfRefreshToken->scope
        ]);
        $accessToken = new AccessTokenHybrid($server, $fakeAccessToken);
        $this->setAccessToken($accessToken);
    }

    /**
     * @return RefreshToken
     */
    public function getXfRefreshToken()
    {
        return $this->xfRefreshToken;
    }
}

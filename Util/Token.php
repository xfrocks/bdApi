<?php

namespace Xfrocks\Api\Util;

use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\TokenType\BearerWithScope;

class Token
{
    /**
     * @param \League\OAuth2\Server\Entity\AccessTokenEntity $accessToken
     * @return array
     *
     * @see \League\OAuth2\Server\TokenType\Bearer::generateResponse()
     * @see BearerWithScope::generateResponse()
     */
    public static function transformLibAccessTokenEntity($accessToken)
    {
        $scopeIds = [];
        foreach ($accessToken->getScopes() as $scope) {
            $scopeIds[] = $scope->getId();
        }

        // TODO: find a better way to keep token response data in sync with BearerWithScope
        return [
            'access_token' => $accessToken->getId(),
            'expires_in' => $accessToken->getExpireTime() - time(),
            'scope' => implode(Listener::$scopeDelimiter, $scopeIds),
            'token_type' => 'Bearer',
        ];
    }
}

<?php

namespace Xfrocks\Api\Util;

use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Storage\SessionStorage;
use Xfrocks\Api\OAuth2\TokenType\BearerWithScope;

class Token
{
    /**
     * @param \League\OAuth2\Server\Entity\AccessTokenEntity $accessToken
     * @param \League\OAuth2\Server\Entity\RefreshTokenEntity|null $refreshToken
     * @return array
     *
     * @see \League\OAuth2\Server\TokenType\Bearer::generateResponse()
     * @see BearerWithScope::generateResponse()
     */
    public static function transformLibAccessTokenEntity($accessToken, $refreshToken = null)
    {
        $scopeIds = [];
        foreach ($accessToken->getScopes() as $scope) {
            $scopeIds[] = $scope->getId();
        }

        // TODO: find a better way to keep token response data in sync with BearerWithScope
        $response = [
            'access_token' => $accessToken->getId(),
            'expires_in' => $accessToken->getExpireTime() - time(),
            'scope' => implode(Listener::$scopeDelimiter, $scopeIds),
            'token_type' => 'Bearer',
        ];

        if ($refreshToken !== null) {
            $return['refresh_token'] = $refreshToken->getId();
        }

        $session = $accessToken->getSession();
        if (!empty($session) && $session->getOwnerType() === SessionStorage::OWNER_TYPE_USER) {
            $response['user_id'] = intval($session->getOwnerId());
        }

        return $response;
    }
}

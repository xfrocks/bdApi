<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Storage\RefreshTokenInterface;
use Xfrocks\Api\Entity\RefreshToken;
use Xfrocks\Api\OAuth2\Entity\AccessTokenHybrid;
use Xfrocks\Api\OAuth2\Entity\RefreshTokenHybrid;

class RefreshTokenStorage extends AbstractStorage implements RefreshTokenInterface
{
    public function get($token)
    {
        /** @var RefreshToken $xfRefreshToken */
        $xfRefreshToken = $this->doXfEntityFind('Xfrocks\Api:RefreshToken', 'refresh_token_text', $token);
        if (empty($xfRefreshToken)) {
            return null;
        }

        return new RefreshTokenHybrid($this->server, $xfRefreshToken);
    }

    public function create($token, $expireTime, $accessToken)
    {
        /** @var AccessTokenHybrid $accessTokenHybrid */
        $accessTokenHybrid = $this->server->getAccessTokenStorage()->get($accessToken);
        $xfToken = $accessTokenHybrid->getXfToken();

        /** @var RefreshToken $xfRefreshToken */
        $xfRefreshToken = $this->app->em()->create('Xfrocks\Api:RefreshToken');
        $xfRefreshToken->bulkSet([
            'client_id' => $xfToken->client_id,
            'refresh_token_text' => $token,
            'expire_date' => $expireTime,
            'user_id' => $xfToken->user_id,
            'scope' => $xfToken->scope
        ]);

        $this->doXfEntitySave($xfRefreshToken);
    }

    public function delete(RefreshTokenEntity $token)
    {
        if ($token instanceof RefreshTokenHybrid) {
            $hybrid = $token;
        } else {
            $hybrid = $this->get($token->getId());
            if (empty($hybrid)) {
                return;
            }
        }

        $this->doXfEntityDelete($hybrid->getXfRefreshToken());
    }
}

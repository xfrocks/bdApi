<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Storage\RefreshTokenInterface;
use Xfrocks\Api\Entity\RefreshToken;
use Xfrocks\Api\OAuth2\Entity\AccessTokenHybrid;
use Xfrocks\Api\OAuth2\Entity\RefreshTokenHybrid;

class RefreshTokenStorage extends AbstractStorage implements RefreshTokenInterface
{
    /**
     * @param string $token
     * @return RefreshTokenHybrid|null
     */
    public function get($token)
    {
        /** @var RefreshToken|null $xfRefreshToken */
        $xfRefreshToken = $this->doXfEntityFind('Xfrocks\Api:RefreshToken', 'refresh_token_text', $token);
        if ($xfRefreshToken === null) {
            return null;
        }

        return new RefreshTokenHybrid($this->server, $xfRefreshToken);
    }

    /**
     * @param string $token
     * @param int $expireTime
     * @param string $accessToken
     * @return RefreshTokenHybrid
     * @throws \XF\PrintableException
     */
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

        return new RefreshTokenHybrid($this->server, $xfRefreshToken);
    }

    /**
     * @param RefreshTokenEntity $token
     * @return void
     * @throws \XF\PrintableException
     */
    public function delete(RefreshTokenEntity $token)
    {
        /** @var RefreshTokenHybrid $hybrid */
        $hybrid = $token;

        if (!$token instanceof RefreshTokenHybrid) {
            $hybrid = $this->get($token->getId());
            if ($hybrid === null) {
                return;
            }
        }

        $this->doXfEntityDelete($hybrid->getXfRefreshToken());
    }
}

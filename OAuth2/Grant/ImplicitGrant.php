<?php

namespace Xfrocks\Api\OAuth2\Grant;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Grant\AbstractGrant;

class ImplicitGrant extends AbstractGrant
{
    protected $identifier = 'implicit';

    protected $responseType = 'token';

    /**
     * @param AccessTokenEntity $accessToken
     * @param array $authParams
     * @return mixed|string
     */
    public function authorize($accessToken, $authParams = [])
    {
        $redirectUri = $authParams['redirect_uri'];

        $queryDelimiter = '#';
        $redirectUri .= (strstr($authParams['redirect_uri'], $queryDelimiter) === false) ? $queryDelimiter : '&';

        $redirectUri .= http_build_query([
            'access_token' => $accessToken->getId(),
            'token_type' => 'Bearer',
            'expires_in' => $accessToken->getExpireTime() - time(),
            'state' => $authParams['state'],
        ]);

        return $redirectUri;
    }

    public function completeFlow()
    {
        throw new \LogicException('This grant does not used this method');
    }
}

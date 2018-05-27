<?php

namespace Xfrocks\Api\OAuth2\Grant;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Util\SecureKey;

class ImplicitGrant extends AbstractGrant
{
    protected $identifier = 'implicit';

    protected $responseType = 'token';

    public function authorize($type, $typeId, $authParams = [])
    {
        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner($type, $typeId);
        $session->associateClient($authParams['client']);

        // Generate the access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());

        foreach ($authParams['scopes'] as $scope) {
            $session->associateScope($scope);
            $accessToken->associateScope($scope);
        }

        $session->save();
        $accessToken->setSession($session);
        $accessToken->save();

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

<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\ClientInterface;
use Xfrocks\Api\OAuth2\Entity\ClientHybrid;

class ClientStorage extends AbstractStorage implements ClientInterface
{
    public function get($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
        /** @var \Xfrocks\Api\Entity\Client|null $xfClient */
        $xfClient = $this->doXfEntityFind('Xfrocks\Api:Client', 'client_id', $clientId);
        if (!$xfClient) {
            return null;
        }

        if ($clientSecret && $xfClient->client_secret !== $clientSecret) {
            return null;
        }

        if ($redirectUri && !$xfClient->isValidRedirectUri($redirectUri)) {
            return null;
        }

        return new ClientHybrid($this->server, $xfClient);
    }

    public function getBySession(SessionEntity $session)
    {
        /** @var SessionStorage $sessionStorage */
        $sessionStorage = $this->server->getSessionStorage();
        $sessionCache = $sessionStorage->getInMemoryCache($session->getId());
        if (!isset($sessionCache[SessionStorage::SESSION_KEY_CLIENT_ID])) {
            return null;
        }

        return $this->get($sessionCache[SessionStorage::SESSION_KEY_CLIENT_ID]);
    }
}

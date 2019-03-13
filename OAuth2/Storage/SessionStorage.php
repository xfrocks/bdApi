<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\SessionInterface;
use Xfrocks\Api\OAuth2\Entity\AccessTokenHybrid;
use Xfrocks\Api\OAuth2\Entity\AuthCodeHybrid;

class SessionStorage extends AbstractStorage implements SessionInterface
{
    const OWNER_TYPE_CLIENT = 'client';
    const OWNER_TYPE_USER = 'user';

    const SESSION_KEY_CLIENT_ID = 'clientId';
    const SESSION_KEY_SCOPES = 'scopes';
    const SESSION_KEY_USER_ID = 'userId';

    /**
     * @var array
     */
    protected $sessions = [];

    /**
     * @param int|string $sessionId
     * @param bool $throw
     * @return array|null
     */
    public function getInMemoryCache($sessionId, $throw = false)
    {
        if (!isset($this->sessions[$sessionId])) {
            if ($throw) {
                throw new \InvalidArgumentException('Session could not be found ' . $sessionId);
            }
            return null;
        }

        return $this->sessions[$sessionId];
    }

    public function getByAccessToken(AccessTokenEntity $accessToken)
    {
        /** @var AccessTokenHybrid $accessTokenHybrid */
        $accessTokenHybrid = $accessToken;
        $xfToken = $accessTokenHybrid->getXfToken();

        $result = new SessionEntity($this->server);

        $sessionId = md5($xfToken->token_text);
        $result->setId($sessionId);

        $userId = $xfToken->user_id;
        $result->setOwner(self::OWNER_TYPE_USER, strval($userId));

        $this->sessions[$sessionId] = [
            self::SESSION_KEY_CLIENT_ID => $xfToken->client_id,
            self::SESSION_KEY_SCOPES => $xfToken->scopes,
            self::SESSION_KEY_USER_ID => $userId
        ];

        return $result;
    }

    public function getByAuthCode(AuthCodeEntity $authCode)
    {
        /** @var AuthCodeHybrid $authCodeHybrid */
        $authCodeHybrid = $authCode;
        $xfAuthCode = $authCodeHybrid->getXfAuthCode();

        $session = new SessionEntity($this->server);

        $sessionId = md5($xfAuthCode->auth_code_text);
        $session->setId($sessionId);

        $userId = $xfAuthCode->user_id;
        $session->setOwner(self::OWNER_TYPE_USER, strval($userId));

        $this->sessions[$sessionId] = [
            self::SESSION_KEY_CLIENT_ID => $xfAuthCode->client_id,
            self::SESSION_KEY_SCOPES => $xfAuthCode->scopes,
            self::SESSION_KEY_USER_ID => $userId
        ];

        return $session;
    }

    public function getScopes(SessionEntity $session)
    {
        $sessionId = strval($session->getId());
        if ($sessionId === '') {
            return [];
        }

        $cache = $this->getInMemoryCache($sessionId);
        if (!is_array($cache) || !isset($cache[self::SESSION_KEY_SCOPES])) {
            return [];
        }

        return $this->scopeBuildObjArrayFromStrArray($cache[self::SESSION_KEY_SCOPES]);
    }

    public function create($ownerType, $ownerId, $clientId, $clientRedirectUri = null)
    {
        $sessionId = count($this->sessions) + 1;

        $cache = [
            self::SESSION_KEY_CLIENT_ID => $clientId,
            self::SESSION_KEY_SCOPES => []
        ];

        if ($ownerType === self::OWNER_TYPE_USER) {
            $cache[self::SESSION_KEY_USER_ID] = $ownerId;
        } else {
            $cache[self::SESSION_KEY_USER_ID] = 0;
        }

        $this->sessions[$sessionId] = $cache;

        return $sessionId;
    }

    public function associateScope(SessionEntity $session, ScopeEntity $scope)
    {
        $sessionId = $session->getId();
        $cache = $this->getInMemoryCache($sessionId, true);

        if (!isset($cache[self::SESSION_KEY_SCOPES])) {
            $cache[self::SESSION_KEY_SCOPES] = [];
        }
        $scopesRef =& $cache[self::SESSION_KEY_SCOPES];

        $scopeId = $scope->getId();
        if (in_array($scopeId, $scopesRef, true)) {
            return;
        }

        $scopesRef[] = $scopeId;
        $this->sessions[$sessionId] = $cache;
    }
}

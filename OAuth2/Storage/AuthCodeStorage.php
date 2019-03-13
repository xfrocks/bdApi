<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AuthCodeInterface;
use Xfrocks\Api\Entity\AuthCode;
use Xfrocks\Api\OAuth2\Entity\AuthCodeHybrid;

class AuthCodeStorage extends AbstractStorage implements AuthCodeInterface
{
    /**
     * @param AuthCodeEntity $token
     * @param ScopeEntity $scope
     * @return void
     * @throws \XF\PrintableException
     */
    public function associateScope(AuthCodeEntity $token, ScopeEntity $scope)
    {
        $hybrid = $this->getHybrid($token);
        $xfAuthCode = $hybrid->getXfAuthCode();

        if ($xfAuthCode->associateScope($scope->getId())) {
            $this->doXfEntitySave($xfAuthCode);
        }
    }

    /**
     * @param string $token
     * @param int $expireTime
     * @param int $sessionId
     * @param string $redirectUri
     * @return void
     * @throws \XF\PrintableException
     */
    public function create($token, $expireTime, $sessionId, $redirectUri)
    {
        /** @var SessionStorage $sessionStorage */
        $sessionStorage = $this->server->getSessionStorage();
        $sessionCache = $sessionStorage->getInMemoryCache($sessionId, true);

        /** @var AuthCode $xfAuthCode */
        $xfAuthCode = $this->app->em()->create('Xfrocks\Api:AuthCode');
        $xfAuthCode->bulkSet([
            'client_id' => $sessionCache[SessionStorage::SESSION_KEY_CLIENT_ID],
            'auth_code_text' => $token,
            'redirect_uri' => $redirectUri,
            'expire_date' => $expireTime,
            'user_id' => $sessionCache[SessionStorage::SESSION_KEY_USER_ID]
        ]);
        $xfAuthCode->setScopes($sessionCache[SessionStorage::SESSION_KEY_SCOPES]);

        $this->doXfEntitySave($xfAuthCode);
    }

    /**
     * @param AuthCodeEntity $token
     * @return void
     * @throws \XF\PrintableException
     */
    public function delete(AuthCodeEntity $token)
    {
        $hybrid = $this->getHybrid($token);
        $this->doXfEntityDelete($hybrid->getXfAuthCode());
    }

    public function get($code)
    {
        /** @var AuthCode|null $xfAuthCode */
        $xfAuthCode = $this->doXfEntityFind('Xfrocks\Api:AuthCode', 'auth_code_text', $code);
        if (!$xfAuthCode) {
            return null;
        }

        return new AuthCodeHybrid($this->server, $xfAuthCode);
    }

    public function getScopes(AuthCodeEntity $token)
    {
        $hybrid = $this->getHybrid($token);
        $xfAuthCode = $hybrid->getXfAuthCode();

        return $this->scopeBuildObjArrayFromStrArray($xfAuthCode->scopes);
    }

    /**
     * @param AuthCodeEntity $token
     * @return AuthCodeHybrid
     */
    protected function getHybrid($token)
    {
        if ($token instanceof AuthCodeHybrid) {
            return $token;
        }

        return $this->get($token->getId());
    }
}

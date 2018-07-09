<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AccessTokenInterface;
use League\OAuth2\Server\Util\SecureKey;
use XF\Repository\User;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\OAuth2\Entity\AccessTokenHybrid;

class AccessTokenStorage extends AbstractStorage implements AccessTokenInterface
{
    protected $fakeTokenTexts = [];

    public function associateScope(AccessTokenEntity $token, ScopeEntity $scope)
    {
        $hybrid = $this->getHybrid($token);
        if (empty($hybrid)) {
            throw new \RuntimeException('Access token cloud not be found ' . $token->getId());
        }

        $xfToken = $hybrid->getXfToken();
        if ($xfToken->associateScope($scope->getId())) {
            $this->doXfEntitySave($xfToken);
        }
    }

    public function create($token, $expireTime, $sessionId)
    {
        /** @var SessionStorage $sessionStorage */
        $sessionStorage = $this->server->getSessionStorage();
        $sessionCache = $sessionStorage->getInMemoryCache($sessionId, true);

        /** @var Token $xfToken */
        $xfToken = $this->app->em()->create('Xfrocks\Api:Token');
        $xfToken->bulkSet([
            'client_id' => $sessionCache[SessionStorage::SESSION_KEY_CLIENT_ID],
            'token_text' => $token,
            'expire_date' => $expireTime,
            'user_id' => $sessionCache[SessionStorage::SESSION_KEY_USER_ID]
        ]);
        $xfToken->setScopes($sessionCache[SessionStorage::SESSION_KEY_SCOPES]);

        $this->doXfEntitySave($xfToken);
    }

    public function delete(AccessTokenEntity $token)
    {
        if (isset($this->fakeTokenTexts[$token->getId()])) {
            return;
        }

        $hybrid = $this->getHybrid($token);
        if (empty($hybrid)) {
            return;
        }

        $this->doXfEntityDelete($hybrid->getXfToken());
    }

    public function generateFakeTokenText()
    {
        $tokenText = SecureKey::generate();

        $this->fakeTokenTexts[$tokenText] = true;

        return $tokenText;
    }

    public function get($token)
    {
        /** @var Token $xfToken */
        $xfToken = $this->doXfEntityFind('Xfrocks\Api:Token', 'token_text', $token);
        if (empty($xfToken)) {
            return null;
        }

        return new AccessTokenHybrid($this->server, $xfToken);
    }

    public function getScopes(AccessTokenEntity $token)
    {
        /** @var AccessTokenHybrid $accessTokenHybrid */
        $accessTokenHybrid = $token;
        return $this->scopeBuildObjArrayFromStrArray($accessTokenHybrid->getXfToken()->scopes);
    }

    /**
     * @param AccessTokenEntity $token
     * @return AccessTokenHybrid|null
     */
    protected function getHybrid($token)
    {
        if ($token instanceof AccessTokenHybrid) {
            return $token;
        }

        return $this->get($token->getId());
    }

    protected function getXfEntityWith()
    {
        /** @var User $userRepo */
        $userRepo = $this->app->repository('XF:User');
        $userWith = $userRepo->getVisitorWith();

        $with = array_map(function ($with) {
            return 'User.' . $with;
        }, $userWith);

        return $with;
    }
}

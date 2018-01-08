<?php

namespace Xfrocks\Api\XF\Session;

use XF\Session\StorageInterface;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\Session\InMemoryStorage;

class Session extends XFCP_Session
{
    const KEY_TOKEN = 'apiToken';

    public function __construct(StorageInterface $storage, array $config = [])
    {
        parent::__construct(new InMemoryStorage(), $config);
    }

    /**
     * @return Token|null
     */
    public function getToken()
    {
        return $this->get(self::KEY_TOKEN);
    }

    /**
     * @return string|null
     */
    public function getTokenText()
    {
        $token = $this->getToken();
        if (empty($token)) {
            return null;
        }

        return $token->token_text;
    }

    /**
     * @param string $scope
     * @return bool
     */
    public function hasScope($scope)
    {
        $token = $this->getToken();
        if (empty($token)) {
            return false;
        }

        return $token->hasScope($scope);
    }

    /**
     * @param Token $token
     */
    public function setToken($token)
    {
        $this->__set(self::KEY_TOKEN, $token);

        if ($token && $token->User) {
            $this->changeUser($token->User);
        }
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Session extends \XF\Session\Session
    {
        // extension hint
    }
}

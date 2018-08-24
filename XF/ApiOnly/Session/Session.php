<?php

namespace Xfrocks\Api\XF\ApiOnly\Session;

use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\Mvc\Session\InMemoryStorage;

class Session extends XFCP_Session
{
    const KEY_TOKEN = 'apiToken';

    /**
     * @var \XF\Session\StorageInterface
     */
    private $_unusedStorage;

    public function __construct(\XF\Session\StorageInterface $storage, array $config = [])
    {
        parent::__construct(new InMemoryStorage(), $config);

        $this->_unusedStorage = $storage;
    }

    public function applyToResponse(\XF\Http\Response $response)
    {
        $headers = $response->headers();
        if (isset($headers['Cache-Control'])) {
            return;
        }

        $response->header('Cache-control', 'private, no-cache, max-age=0');
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
     * @param Token|null $token
     */
    public function setToken($token)
    {
        $this->__set(self::KEY_TOKEN, $token);

        if ($token && $token->user_id > 0) {
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

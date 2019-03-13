<?php

namespace Xfrocks\Api\Mvc\Session;

use XF\Session\StorageInterface;

class InMemoryStorage implements StorageInterface
{
    /**
     * @param mixed $sessionId
     * @return bool
     */
    public function getSession($sessionId)
    {
        return false;
    }

    /**
     * @param mixed $sessionId
     * @return void
     */
    public function deleteSession($sessionId)
    {
        // no op
    }

    /**
     * @param mixed $sessionId
     * @param array $data
     * @param mixed $lifetime
     * @param mixed $existing
     * @return void
     */
    public function writeSession($sessionId, array $data, $lifetime, $existing)
    {
        // no op
    }

    /**
     * @return void
     */
    public function deleteExpiredSessions()
    {
        // no op
    }
}

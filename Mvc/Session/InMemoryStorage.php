<?php

namespace Xfrocks\Api\Mvc\Session;

use XF\Session\StorageInterface;

class InMemoryStorage implements StorageInterface
{
    public function getSession($sessionId)
    {
        return false;
    }

    public function deleteSession($sessionId)
    {
        // no op
    }

    public function writeSession($sessionId, array $data, $lifetime, $existing)
    {
        // no op
    }

    public function deleteExpiredSessions()
    {
        // no op
    }
}

<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;

class Client extends Repository
{
    /**
     * @param int $userId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserClients($userId)
    {
        $finder = $this->finder('Xfrocks\Api:Client');
        $finder->where('user_id', $userId);

        return $finder;
    }
}

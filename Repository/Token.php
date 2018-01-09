<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;

class Token extends Repository
{
    /**
     * @return int
     */
    public function deleteExpiredTokens()
    {
        return $this->db()->delete('xf_bdapi_token', 'expire_date < ?', \XF::$time);
    }
}

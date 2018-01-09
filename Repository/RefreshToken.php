<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;

class RefreshToken extends Repository
{
    /**
     * @return int
     */
    public function deleteExpiredRefreshTokens()
    {
        return $this->db()->delete('xf_bdapi_refresh_token', 'expire_date < ?', \XF::$time);
    }
}

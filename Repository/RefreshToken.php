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

    /**
     * @param string $clientId
     * @param int $userId
     * @return int
     */
    public function deleteRefreshTokens($clientId, $userId)
    {
        return $this->db()->delete(
            'xf_bdapi_refresh_token',
            'client_id = ? AND user_id = ?',
            [$clientId, $userId]
        );
    }
}

<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;

class AuthCode extends Repository
{
    /**
     * @return int
     */
    public function deleteExpiredAuthCodes()
    {
        return $this->db()->delete('xf_bdapi_auth_code', 'expire_date < ?', \XF::$time);
    }

    /**
     * @param string $clientId
     * @param int $userId
     * @return int
     */
    public function deleteAuthCodes($clientId, $userId)
    {
        return $this->db()->delete(
            'xf_bdapi_auth_code',
            'client_id = ? AND user_id = ?',
            [$clientId, $userId]
        );
    }
}

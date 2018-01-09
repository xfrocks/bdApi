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
}

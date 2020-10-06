<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;

class UserScope extends Repository
{
    /**
     * @param string $clientId
     * @param int $userId
     * @param string $scope
     * @return int
     */
    public function deleteUserScope($clientId, $userId, $scope)
    {
        return $this->db()->delete(
            'xf_bdapi_user_scope',
            'client_id = ? AND user_id = ? AND scope = ?',
            [$clientId, $userId, $scope]
        );
    }
}

<?php

class bdApi_Model_User extends XenForo_Model
{
    public function countUsersBeingFollowedByUserId($userId)
    {
        return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_follow
			WHERE user_id = ?
		', $userId);
    }

    public function getFollowedUserProfiles($userId, $fetchOptions = array())
    {
        $orderClause = $this->prepareUserOrderOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $sql = "
            SELECT user.user_id, user_follow.follow_date
            FROM xf_user_follow AS user_follow
            INNER JOIN xf_user AS user ON
                (user.user_id = user_follow.follow_user_id AND user.is_banned = 0)
            WHERE user_follow.user_id = $userId
            $orderClause
        ";

        return $this->fetchAllKeyed($this->limitQueryResults($sql, $limitOptions['limit'], $limitOptions['offset']), 'user_id');
    }

    public function getUsersFollowingUserId($userId, $fetchOptions = array())
    {
        $orderClause = $this->prepareUserOrderOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $sql = "
            SELECT user.user_id, user_follow.follow_date
            FROM xf_user_follow AS user_follow
            INNER JOIN xf_user AS user ON
                (user.user_id = user_follow.user_id AND user.is_banned = 0)
            WHERE user_follow.follow_user_id = $userId
            $orderClause
        ";

        return $this->fetchAllKeyed($this->limitQueryResults($sql, $limitOptions['limit'], $limitOptions['offset']), 'user_id');
    }

    public function prepareUserOrderOptions(array $fetchOptions, $defaultOrderSql = '')
    {
        $choices = array('follow_date' => 'user_follow.follow_date');

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }
}

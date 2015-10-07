<?php

class bdApi_Extend_Model_UserIgnore extends XFCP_bdApi_Extend_Model_UserIgnore
{
    public function bdApi_countIgnoredUsers($userId)
    {
        return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_ignored
			WHERE user_id = ?
		', $userId);
    }
}

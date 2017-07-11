<?php

class bdApi_Extend_Model_ForumWatch extends XFCP_bdApi_Extend_Model_ForumWatch
{
    public function bdApi_countUserForumWatchByUser($userId)
    {
        return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_forum_watch
			WHERE user_id = ?
		', $userId);
    }

    public function prepareApiDataForForumWatches(array $data, array $forumWatch)
    {
        $data['follow']['post'] = $forumWatch['notify_on'] == 'message';
        $data['follow']['alert'] = !empty($forumWatch['send_alert']);
        $data['follow']['email'] = !empty($forumWatch['send_email']);

        return $data;
    }
}

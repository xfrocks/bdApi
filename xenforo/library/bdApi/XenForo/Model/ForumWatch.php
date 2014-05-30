<?php

class bdApi_XenForo_Model_ForumWatch extends XFCP_bdApi_XenForo_Model_ForumWatch
{
	public function prepareApiDataForForumWatches(array $data, array $forumWatch)
	{
		$data['follow']['post'] = $forumWatch['notify_on'] == 'message';
		$data['follow']['alert'] = !empty($forumWatch['send_alert']);
		$data['follow']['email'] = !empty($forumWatch['send_email']);

		return $data;
	}

}

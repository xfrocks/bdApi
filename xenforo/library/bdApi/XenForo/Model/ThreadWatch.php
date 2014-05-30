<?php

class bdApi_XenForo_Model_ThreadWatch extends XFCP_bdApi_XenForo_Model_ThreadWatch
{
	public function prepareApiDataForThreadWatches(array $data, array $threadWatch)
	{
		$data['follow']['alert'] = true;
		$data['follow']['email'] = !empty($threadWatch['email_subscribe']);

		return $data;
	}

	public function getThreadsWatchedByUser($userId, $newOnly, array $fetchOptions = array())
	{
		if (isset($fetchOptions['watchUserId']))
		{
			unset($fetchOptions['watchUserId']);
		}

		return parent::getThreadsWatchedByUser($userId, $newOnly, $fetchOptions);
	}

}

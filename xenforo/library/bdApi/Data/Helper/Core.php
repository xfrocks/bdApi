<?php

class bdApi_Data_Helper_Core
{
	/**
	 * Adds system information into response data (both XML and JSON)
	 *
	 * @param array $data
	 */
	public static function addDefaultResponse(array &$data)
	{
		if (XenForo_Application::debugMode())
		{
			$data['debug'] = XenForo_Debug::getDebugTemplateParams();
		}

		$data['system_info'] = array(
			'visitor_id' => XenForo_Visitor::getUserId(),
			'time' => XenForo_Application::$time,
		);
	}

	/**
	 * Builds and adds the navigation for api data
	 *
	 * @param XenForo_Input $input
	 * @param array $data
	 * @param int $perPage
	 * @param int $totalItems
	 * @param int $page
	 * @param string $linkType
	 * @param mixed $linkData
	 * @param array $linkParams
	 * @param array $options
	 */
	public static function addPageLinks(XenForo_Input $input, array &$data, $perPage, $totalItems, $page, $linkType, $linkData = null, array $linkParams = array(), array $options = array())
	{
		if (empty($perPage))
		{
			return;
		}

		$pageNav = array();

		$inputData = $input->filter(array(
			'fields_include' => XenForo_Input::STRING,
			'fields_exclude' => XenForo_Input::STRING,
		));
		if (!empty($inputData['fields_include']))
		{
			$linkParams['fields_include'] = $inputData['fields_include'];
		}
		elseif (!empty($inputData['fields_exclude']))
		{
			$linkParams['fields_exclude'] = $inputData['fields_exclude'];
		}

		if (empty($page))
		{
			$page = 1;
		}

		$pageNav['pages'] = ceil($totalItems / $perPage);

		if ($pageNav['pages'] <= 1)
		{
			// do not do anything if there is only 1 page (or no pages)
			return;
		}

		if ($page > 1)
		{
			// a previous link should only be added if we are not at page 1
			$pageNav['prev'] = bdApi_Link::buildApiLink($linkType, $linkData, array_merge($linkParams, array('page' => $page - 1)));
		}

		if ($page < $pageNav['pages'])
		{
			// a next link should only be added if we are not at the last page
			$pageNav['next'] = bdApi_Link::buildApiLink($linkType, $linkData, array_merge($linkParams, array('page' => $page + 1)));
		}

		// add the page navigation into `links`
		// the data may have existing links or not
		// we simply don't care
		if (empty($data['links']))
		{
			$data['links'] = array();
		}
		$data['links'] += $pageNav;
	}

	/**
	 * Filters data into another array with value from specified keys only
	 *
	 * @param array $data
	 * @param array $publicKeys
	 */
	public static function filter(array $data, array $publicKeys)
	{
		$filteredData = array();

		foreach ($publicKeys as $publicKey => $mappedKey)
		{
			if (is_int($publicKey))
			{
				// backward compatible with previous versions
				// where $publicKeys is just an array of keys
				// (with no key value pair)
				$publicKey = $mappedKey;
			}

			if (isset($data[$publicKey]))
			{
				$filteredData[$mappedKey] = $data[$publicKey];
			}
		}

		return $filteredData;
	}

}

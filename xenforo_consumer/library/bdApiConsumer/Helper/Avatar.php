<?php

class bdApiConsumer_Helper_Avatar
{
	protected static $_helperOriginal = null;

	public static function setupHelper()
	{
		self::$_helperOriginal = XenForo_Template_Helper_Core::$helperCallbacks['avatar'];
		if (self::$_helperOriginal[0] === 'self')
		{
			self::$_helperOriginal[0] = 'XenForo_Template_Helper_Core';
		}

		XenForo_Template_Helper_Core::$helperCallbacks['avatar'] = array(
			__CLASS__,
			'helperAvatarUrl'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['bdapiconsumer_avatarresize'] = array(
			__CLASS__,
			'helperAvatarResize'
		);
	}

	public static function helperAvatarUrl($user, $size, $forceType = null, $canonical = false)
	{
		if (!empty($user['gravatar']))
		{
			$avatarUrl = self::parseGravatar($user['gravatar']);

			if (!empty($avatarUrl))
			{
				return XenForo_Template_Helper_Core::callHelper('bdapiconsumer_avatarresize', array(
					$avatarUrl,
					$size
				));
			}
		}

		return call_user_func(self::$_helperOriginal, $user, $size, $forceType, $canonical);
	}

	public static function helperAvatarResize($avatarUrl, $size)
	{
		if (defined('BDIMAGE_IS_WORKING'))
		{
			// use [bd] Image to resize avatars
			// TODO: read XenForo_Model_Avatar::$_sizes for this?
			switch ($size)
			{
				case 's':
					$size = 48;
					break;
				case 'm':
					$size = 96;
					break;
				case 'l':
				default:
					if (!is_int($size))
					{
						$size = 192;
					}
			}

			$avatarUrl = bdImage_Integration::buildThumbnailLink($avatarUrl, $size);
		}

		return $avatarUrl;
	}

	public static function getAvatarUrlFromAuthExtra(array $extra)
	{
		if (!empty($extra['links']['avatar_big']))
		{
			return $extra['links']['avatar_big'];
		}
		elseif (!empty($extra['links']['avatar']))
		{
			return $extra['links']['avatar'];
		}

		return false;
	}

	public static function getGravatar($userId, $avatarUrl)
	{
		// try the first variant
		$first = sprintf('%s@p.i', self::_encode($avatarUrl));
		if (strlen($first) <= 120)
		{
			// too long
			return $first;
		}

		// try the second variant if the first failed
		$second = sprintf('%d-%d@p.i', $userId, XenForo_Application::$time);
		return $second;
	}

	public static function parseGravatar($gravatar)
	{
		if (!preg_match('#^(.+)@p\.i$#', $gravatar, $gravatarMatches))
		{
			return false;
		}

		$part = $gravatarMatches[1];

		if (preg_match('#^(\d+)-(\d+)$#', $part, $partMatches))
		{
			// second variant
			return XenForo_Link::buildPublicLink('canonical:members/external-avatar', array('user_id' => $partMatches[1])) . '?' . $partMatches[2];
		}

		// first variant
		return self::_decode($part);
	}

	protected static function _encode($url)
	{
		return 'b64,' . base64_encode($url);
	}

	protected static function _decode($encoded)
	{
		if (strpos($encoded, 'b64,') === 0)
		{
			return base64_decode(substr($encoded, 4));
		}

		return false;
	}

}

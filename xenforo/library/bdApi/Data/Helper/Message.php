<?php

class bdApi_Data_Helper_Message
{
	public static function getHtml(&$message)
	{
		static $bbCodeParser = false;

		if ($bbCodeParser === false)
		{
			$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base'));
		}

		return XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($message, $bbCodeParser);
	}

	public static function getPlainText($bbCode)
	{
		$config = XenForo_Application::getConfig();
		$useSnippet = $config->get('bdApi_useSnippet');

		if (!empty($useSnippet))
		{
			return XenForo_Template_Helper_Core::callHelper('snippet', array(
				$bbCode,
				0,
				array(
					'stripQuote' => true,
					'stripHtml' => false,
				)
			));
		}
		else
		{
			// from XenForo_Helper_String::bbCodeStrip
			$string = $bbCode;

			$string = preg_replace('#\[(attach|media|img)[^\]]*\].*\[/\\1\]#siU', '', $string);

			while ($string != ($newString = preg_replace('#\[([a-z0-9]+)(=[^\]]*)?\](.*)\[/\1\]#siU', '\3', $string)))
			{
				$string = $newString;
			}

			$string = str_replace('[*]', '', $string);
			$string = trim($string);
			$string = XenForo_Helper_String::censorString($string);

			return htmlspecialchars($string);
		}
	}

}

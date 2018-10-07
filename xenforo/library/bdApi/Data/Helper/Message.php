<?php

class bdApi_Data_Helper_Message
{
    public static function getHtml(&$message, array $bbCodeOptions = array())
    {
        static $bbCodeParser = false;

        if ($bbCodeParser === false) {
            $formatter = XenForo_BbCode_Formatter_Base::create('Base', array(
                'view' => bdApi_Template_Simulation_View::create(),
            ));

            if (XenForo_Application::$versionId >= 1020000) {
                $bbCodeParser = XenForo_BbCode_Parser::create($formatter);
            } else {
                $bbCodeParser = new XenForo_BbCode_Parser($formatter);
            }
        }

        if (!isset($bbCodeOptions['states'])) {
            $bbCodeOptions['states'] = array();
        }
        $statesRef =& $bbCodeOptions['states'];
        if (!isset($statesRef['lightBox'])) {
            $statesRef['lightBox'] = false;
        }
        if (!isset($statesRef['shortenUrl'])) {
            $statesRef['shortenUrl'] = false;
        }

        return XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($message, $bbCodeParser, $bbCodeOptions);
    }

    public static function getPlainText($bbCode)
    {
        $config = XenForo_Application::getConfig();
        $useSnippet = $config->get('bdApi_useSnippet');

        if (!empty($useSnippet)) {
            $html = XenForo_Template_Helper_Core::callHelper('snippet', array(
                $bbCode,
                0,
                array(
                    'stripQuote' => true,
                    'stripHtml' => false,
                )
            ));

            return htmlspecialchars_decode($html, ENT_QUOTES);
        } else {
            // from XenForo_Helper_String::bbCodeStrip
            $string = $bbCode;

            $string = preg_replace('#\[(attach|media|img)[^\]]*\].*\[/\\1\]#siU', '', $string);

            while ($string != ($newString = preg_replace('#\[([a-z0-9]+)(=[^\]]*)?\](.*)\[/\1\]#siU', '\3', $string))) {
                $string = $newString;
            }

            $string = str_replace('[*]', '', $string);
            $string = trim($string);
            $string = XenForo_Helper_String::censorString($string);

            return $string;
        }
    }
}

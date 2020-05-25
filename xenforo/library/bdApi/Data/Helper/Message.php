<?php

class bdApi_Data_Helper_Message
{
    public static function getHtml(&$message, array $bbCodeOptions = array())
    {
        static $bbCodeParsers = array();
        $formatterId = isset($bbCodeOptions['formatter']) ? $bbCodeOptions['formatter'] : 'Base';

        if (!isset($bbCodeParsers[$formatterId])) {
            $formatter = XenForo_BbCode_Formatter_Base::create($formatterId, array(
                'view' => bdApi_Template_Simulation_View::create(),
            ));

            if (XenForo_Application::$versionId >= 1020000) {
                $bbCodeParsers[$formatterId] = XenForo_BbCode_Parser::create($formatter);
            } else {
                $bbCodeParsers[$formatterId] = new XenForo_BbCode_Parser($formatter);
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

        return XenForo_ViewPublic_Helper_Message::getBbCodeWrapper(
            $message,
            $bbCodeParsers[$formatterId],
            $bbCodeOptions
        );
    }

    /**
     * @param string $bbCode
     * @return string
     * @see XenForo_Helper_String::bbCodeStrip
     */
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
            $string = XenForo_Helper_String::bbCodeStrip($bbCode, true);
            $string = XenForo_Helper_String::censorString($string);

            return $string;
        }
    }
}

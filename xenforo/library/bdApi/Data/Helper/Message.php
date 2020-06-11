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
        $string = XenForo_Helper_String::bbCodeStrip($bbCode, true);
        $string = XenForo_Helper_String::censorString($string);

        // remove unviewable's placeholders, it's unlikely they are expected in api contexts
        $string = preg_replace('#\[(attach|media|img|spoiler)\]#i', '', $string);

        return $string;
    }
}

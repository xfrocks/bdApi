<?php

class bdApi_Template_Simulation_Template extends XenForo_Template_Public
{
    public static $bdApi_visitor = null;
    public static $bdApi_mapping = array(
        'bb_code_tag_attach' => 'bdapi_bb_code_tag_attach',
    );

    protected static $_bdApi_requiredExternalActiveContext = null;
    protected static $_bdApi_requiredExternalsByContext = array();

    public function bdApi_clearRequiredExternalsByContext()
    {
        self::$_bdApi_requiredExternalsByContext = array();
    }

    public function bdApi_getRequiredExternalsByContext($context)
    {
        $ref =& self::$_bdApi_requiredExternalsByContext;
        if (!isset($ref[$context])) {
            return array();
        }

        $result = $ref[$context];
        unset($ref[$context]);

        return $result;
    }

    public function bdApi_setRequiredExternalContext($context)
    {
        self::$_bdApi_requiredExternalActiveContext = $context;
    }

    public function addRequiredExternal($type, $requirement)
    {
        $context = self::$_bdApi_requiredExternalActiveContext;
        if ($context !== null) {
            $ref =& self::$_bdApi_requiredExternalsByContext;
            if (empty($ref[$context][$type])) {
                $ref[$context][$type] = array();
            }
            $ref[$context][$type][] = $requirement;
        }

        parent::addRequiredExternal($type, $requirement);
    }

    public function clearRequiredExternalsForApi()
    {
        $this->_setRequiredExternals(array());
    }

    public function getRequiredExternalsAsHtmlForApi()
    {
        $required = $this->_getRequiredExternals();
        $html = '';

        $extraData = self::getExtraContainerData();
        if (!empty($extraData['head'])) {
            foreach ($extraData['head'] as $head) {
                $html .= utf8_trim($head);
            }
        }

        foreach (array_keys($required) as $type) {
            $html .= $this->getRequiredExternalsAsHtml($type);
        }

        return $html;
    }

    public function getRequiredCssUrl(array $requirements)
    {
        $cssUrl = parent::getRequiredCssUrl($requirements);
        return XenForo_Link::convertUriToAbsoluteUri($cssUrl, true);
    }

    public function __construct($templateName, array $params = array())
    {
        if (isset(self::$bdApi_mapping[$templateName])) {
            $templateName = self::$bdApi_mapping[$templateName];
        }

        if (self::$bdApi_visitor !== null) {
            $params['visitor'] = self::$bdApi_visitor;
        }

        $languageId = 0;
        if (!empty($params['visitor']['language_id'])) {
            $languageId = $params['visitor']['language_id'];
        }
        if (empty($languageId)) {
            $languageId = XenForo_Application::getOptions()->get('defaultLanguageId');
        }

        $params['xenOptions'] = XenForo_Application::getOptions()->getOptions();

        parent::__construct(sprintf('__%s_%d', $templateName, $languageId), $params);
    }

    protected function _getTemplatesFromDataSource(array $templateList)
    {
        $db = XenForo_Application::getDb();

        $listByLanguageId = array();
        foreach ($templateList as $template) {
            if (preg_match('#^__(.+)_(\d+)$#', $template, $matches)) {
                $templateName = $matches[1];
                $languageId = $matches[2];

                if (!isset($listByLanguageId[$languageId])) {
                    $listByLanguageId[$languageId] = array();
                }
                $listByLanguageId[$languageId][] = $templateName;
            }
        }

        $results = array();

        foreach ($listByLanguageId as $languageId => $templateNames) {
            $templates = $db->fetchPairs('
				SELECT title, template_compiled
				FROM xf_template_compiled
				WHERE title IN (' . $db->quote($templateNames) . ')
					AND style_id = ?
					AND language_id = ?
			', array(
                XenForo_Application::getOptions()->get('defaultStyleId'),
                $languageId,
            ));

            foreach ($templates as $title => $compiled) {
                $results[sprintf('__%s_%d', $title, $languageId)] = $compiled;
            }
        }

        return $results;
    }

    protected function _loadTemplateFilePath($templateName)
    {
        if ($this->_usingTemplateFiles() AND preg_match('#^__(.+)_(\d+)$#', $templateName, $matches)) {
            $templateName = $matches[1];
            $styleId = XenForo_Application::getOptions()->get('defaultStyleId');
            $languageId = $matches[2];

            return XenForo_Template_FileHandler::get($templateName, $styleId, $languageId);
        } else {
            return '';
        }
    }
}

<?php

class bdApi_Template_Simulation_Template extends XenForo_Template_Public
{
    public static $bdApi_visitor = null;

    protected static $_bdApi_renderContexts = array();
    protected static $_bdApi_renderContextCount = 0;
    protected static $_bdApi_renderContextData = array();

    /**
     * @param string $prefix
     * @return string
     */
    public function bdApi_setRenderContext($prefix = '')
    {
        self::$_bdApi_renderContextCount++;
        $context = sprintf('%s_%d', $prefix, self::$_bdApi_renderContextCount);
        self::$_bdApi_renderContexts[] = $context;

        return $context;
    }

    public function bdApi_unsetAndGetRequiredExternalsByContext($context)
    {
        $ref =& self::$_bdApi_renderContextData;
        $unset = $this->bdApi_unsetRenderContext($context);
        $result = array();

        foreach ($unset as $unsetContext) {
            if (empty($ref[$unsetContext])) {
                continue;
            }

            foreach ($ref[$unsetContext] as $type => $values) {
                if (!isset($result[$type])) {
                    $result[$type] = array();
                }
                $result[$type] += $values;
            }

            unset($ref[$unsetContext]);
        }

        return $result;
    }

    /**
     * @param string $context
     * @return array
     */
    public function bdApi_unsetRenderContext($context)
    {
        $unset = array();
        while (true) {
            $last = array_pop(self::$_bdApi_renderContexts);
            if (is_string($last)) {
                $unset[] = $last;
            }
            if (empty($last) || $last === $context) {
                break;
            }
        }

        return $unset;
    }

    public function addRequiredExternal($type, $requirement)
    {
        $context = end(self::$_bdApi_renderContexts);
        if (is_string($context)) {
            $ref =& self::$_bdApi_renderContextData;
            if (empty($ref[$context][$type])) {
                $ref[$context][$type] = array();
            }
            $ref[$context][$type][$requirement] = true;
        }

        parent::addRequiredExternal($type, $requirement);
    }

    public function clearRequiredExternalsForApi()
    {
        if (isset(self::$_extraData['head'])) {
            unset(self::$_extraData['head']);
        }

        self::$_bdApi_renderContexts = array('root');
        self::$_bdApi_renderContextData = array();

        $this->_setRequiredExternals(array());
    }

    public function getRequiredExternalsAsHtmlForApi()
    {
        $required = array();

        foreach (self::$_bdApi_renderContextData as $context => $contextData) {
            foreach ($contextData as $type => $values) {
                if (!isset($required[$type])) {
                    $required[$type] = array();
                }
                $required[$type] += $values;
            }
        }

        $html = '';

        foreach (array('css', 'js', 'head') as $type) {
            if (!isset($required[$type])) {
                continue;
            }
            switch ($type) {
                case 'css':
                    $html .= $this->getRequiredCssAsHtml($this->getRequiredCssUrl(array_keys($required[$type])));
                    break;
                case 'head':
                    $html .= implode('', $required[$type]);
                    break;
                case 'js':
                    $html .= $this->getRequiredJavaScriptAsHtml(array_keys($required[$type]));
                    break;
            }
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

        $params['isApiTemplateSimulation'] = true;

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

    protected function _mergeExtraContainerData(array $extraData)
    {
        if (isset($extraData['head'])) {
            $context = end(self::$_bdApi_renderContexts);
            if (is_string($context)) {
                $ref =& self::$_bdApi_renderContextData;
                $type = 'head';
                if (empty($ref[$context][$type])) {
                    $ref[$context][$type] = array();
                }
                foreach ($extraData['head'] as $key => $value) {
                    $ref[$context][$type][$key] = utf8_trim($value);
                }
            }
        }

        parent::_mergeExtraContainerData($extraData);
    }
}

<?php

class bdApi_Extend_BbCode_Formatter_Base extends XFCP_bdApi_Extend_BbCode_Formatter_Base
{
    public function renderTree(array $tree, array $extraStates = array())
    {
        /** @var bdApi_Template_Simulation_View $view */
        $view = $this->_view;
        /** @var bdApi_Template_Simulation_Template $noNameTemplate */
        $noNameTemplate = null;
        if (!empty($view)) {
            $noNameTemplate = $view->createTemplateObject('');
            $noNameTemplate->clearRequiredExternalsForApi();
        }

        $rendered = parent::renderTree($tree, $extraStates);

        if (!empty($noNameTemplate)) {
            $requiredExternalsHtml = $noNameTemplate->getRequiredExternalsAsHtmlForApi();
            if (strlen($requiredExternalsHtml) > 0) {
                $rendered = sprintf('<!--%s-->%s', preg_replace('/(\n|\t)/', '', $requiredExternalsHtml), $rendered);
            }
        }

        return $rendered;
    }
}
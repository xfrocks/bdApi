<?php

namespace Xfrocks\Api\XF\ApiOnly\BbCode\Renderer;

use Xfrocks\Api\XF\ApiOnly\Template\Templater;

class Html extends XFCP_Html
{
    public function renderAst(array $ast, \XF\BbCode\RuleSet $rules, array $options = [])
    {
        /** @var Templater $templater */
        $templater = $this->getTemplater();
        $templater->clearRequiredExternalsForApi();

        $rendered = parent::renderAst($ast, $rules, $options);

        $requiredExternalsHtml = $templater->getRequiredExternalsAsHtmlForApi();
        if (strlen($requiredExternalsHtml) > 0) {
            $rendered = sprintf('<!--%s-->%s', $requiredExternalsHtml, $rendered);
        }

        return $rendered;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Html extends \XF\BbCode\Renderer\Html
    {
        // extension hint
    }
}

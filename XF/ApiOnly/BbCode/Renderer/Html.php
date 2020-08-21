<?php

namespace Xfrocks\Api\XF\ApiOnly\BbCode\Renderer;

use Xfrocks\Api\Listener;
use Xfrocks\Api\Util\Crypt;
use Xfrocks\Api\XF\ApiOnly\Template\Templater;

class Html extends XFCP_Html
{
    const CHR_TTL = 86400;

    /** @var null|array */
    private $XfrocksApiChr = null;

    public function renderAst(array $ast, \XF\BbCode\RuleSet $rules, array $options = [])
    {
        /** @var Templater $templater */
        $templater = $this->getTemplater();
        $templater->requiredExternalsReset();

        $rendered = parent::renderAst($ast, $rules, $options);

        $requiredExternalsHtml = $templater->requiredExternalsGetHtml();
        if (strlen($requiredExternalsHtml) > 0) {
            $rendered = sprintf('<!--%s-->%s', $requiredExternalsHtml, $rendered);
        }

        return $rendered;
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return string
     * @throws \XF\PrintableException
     */
    public function renderTagMedia(array $children, $option, array $tag, array $options)
    {
        if ($this->XfrocksApiChr === null) {
            return parent::renderTagMedia($children, $option, $tag, $options);
        }

        /** @var Templater $templater */
        $templater = $this->getTemplater();
        $backup = $templater->requiredExternalsReset();

        $html = parent::renderTagMedia($children, $option, $tag, $options);
        $requiredExternals = $templater->requiredExternalsReset($backup);

        return $this->renderXfrocksApiChr($html, $requiredExternals);
    }

    /**
     * @param string $html
     * @param array $requiredExternals
     * @return string
     * @throws \XF\PrintableException
     */
    public function renderXfrocksApiChr($html, array $requiredExternals)
    {
        $requiredExternalsTrimmed = [];
        foreach ($requiredExternals as $key => $value) {
            if (is_array($value) && count($value) === 0) {
                continue;
            }
            $requiredExternalsTrimmed[$key] = $value;
        }

        $timestamp = time() + self::CHR_TTL;
        $linkParams = [
            'html' => Crypt::encryptTypeOne($html, $timestamp),
            'required_externals' => count($requiredExternalsTrimmed) > 0 ? Crypt::encryptTypeOne(
                \GuzzleHttp\json_encode($requiredExternalsTrimmed),
                $timestamp
            ) : null,
            'timestamp' => $timestamp,
        ];
        $templater = $this->getTemplater();
        $link = $templater->fnLinkType($templater, $escape, Listener::$routerType, 'tools/chr', null, $linkParams);

        $attributes = 'data-chr="true"';
        $label = '';
        if (preg_match('#https?://([^/]+)/#', $html, $domainMatches) === 1) {
            $domain = htmlentities($domainMatches[1]);
            $attributes .= sprintf(' data-chr-domain="%s"', $domain);
            $label = $domain;
        }
        if ($label === '') {
            $label = md5($html);
        }

        /** @noinspection HtmlUnknownAttribute, HtmlUnknownTarget */
        return sprintf(
            "<div class=\"XfrocksApiChr\"><a %s href=\"%s\">%s</a></div>\n",
            $attributes,
            htmlentities($link),
            $label
        );
    }

    public static function factory(\XF\App $app)
    {
        $renderer = parent::factory($app);

        // get Api-Bb-Code-Chr header
        $chrHeader = $app->request()->getServer('HTTP_API_BB_CODE_CHR');
        if (is_string($chrHeader)) {
            $parts = preg_split('/,/', $chrHeader, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($parts) && count($parts) > 0) {
                $renderer->XfrocksApiChr = $parts;
            }
        }

        return $renderer;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Html extends \XF\BbCode\Renderer\Html
    {
        // extension hint
    }
}

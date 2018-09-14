<?php

namespace Xfrocks\Api\XF\Transform;

class Page extends AbstractNode
{
    const KEY_VIEW_COUNT = 'page_view_count';

    const DYNAMIC_KEY_PAGE_HTML = 'page_html';

    public function getMappings($context)
    {
        $mappings = parent::getMappings($context);

        $mappings['view_count'] = self::KEY_VIEW_COUNT;
        $mappings[] = self::DYNAMIC_KEY_PAGE_HTML;

        return $mappings;
    }

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XF\Entity\Page $page */
        $page = $context->getSource();

        if ($key === self::DYNAMIC_KEY_PAGE_HTML) {
            return $this->app->templater()->renderTemplate('public:' . $page->getTemplateName(), [
                'page' => $page
            ]);
        }

        return parent::calculateDynamicValue($context, $key);
    }

    public function collectLinks($context)
    {
        $links = parent::collectLinks($context);

        /** @var \XF\Entity\Page $page */
        $page = $context->getSource();

        $links['sub-pages'] = $this->buildApiLink('pages', null, [
            'parent_page_id' => $page->node_id
        ]);

        return $links;
    }

    protected function getNameSingular()
    {
        return 'page';
    }

    protected function getRoutePrefix()
    {
        return 'pages';
    }
}

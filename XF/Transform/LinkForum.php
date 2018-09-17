<?php

namespace Xfrocks\Api\XF\Transform;

class LinkForum extends AbstractNode
{
    public function collectLinks($context)
    {
        $links = parent::collectLinks($context);

        /** @var \XF\Entity\LinkForum $linkForum */
        $linkForum = $context->getSource();
        if ($linkForum->link_url) {
            $links['target'] = $linkForum->link_url;
        } else {
            $links['target'] = $this->buildPublicLink('link-forums', $linkForum);
        }

        return $links;
    }

    protected function getNameSingular()
    {
        return 'link';
    }

    protected function getRoutePrefix()
    {
        return 'link-forums';
    }
}

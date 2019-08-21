<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class Tag extends AbstractHandler
{
    public function canView(TransformContext $context)
    {
        return true;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            'tag_id' => 'tag_id',
            'tag' => 'tag_text',
            'use_count' => 'tag_use_count'
        ];
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XF\Entity\Tag $tag */
        $tag = $context->getSource();

        return [
            self::LINK_DETAIL => $this->buildApiLink('tags', $tag),
            self::LINK_PERMALINK => $this->buildPublicLink('tags', $tag)
        ];
    }
}

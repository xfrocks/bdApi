<?php

namespace Xfrocks\Api\XF\Transform;

use XF\Entity\Node;
use Xfrocks\Api\Transform\AbstractHandler;

abstract class AbstractNode extends AbstractHandler
{
    const LINK_SUB_CATEGORIES = 'sub-categories';
    const LINK_SUB_FORUMS = 'sub-forums';

    public function getMappings()
    {
        return [
            'node_id' => $this->getNameSingular() . '_id',
            'title' => $this->getNameSingular() . '_title',
            'description' => $this->getNameSingular() . '_description'
        ];
    }

    public function collectLinks()
    {
        /** @var Node $node  */
        $node = $this->source;

        $links = [
            self::LINK_PERMALINK => $this->buildApiLink($this->getRoutePrefix(), $this->source),
            self::LINK_DETAIL => $this->buildApiLink($this->getRoutePrefix(), $this->source),

            self::LINK_SUB_CATEGORIES => $this->buildApiLink('categories', [], ['parent_category_id' => $node->node_id]),
            self::LINK_SUB_FORUMS => $this->buildApiLink('forums', [], ['parent_forum_id' => $node->node_id])
        ];

        return $links;
    }

    public function collectPermissions()
    {
        $perms = [];

        if (is_callable([$this->source, 'canView'])) {
            $perms[self::PERM_VIEW] = call_user_func([$this->source, 'canView']);
        }

        $visitor = \XF::visitor();
        $perms[self::PERM_EDIT] = $visitor->hasAdminPermission('node');
        $perms[self::PERM_DELETE] = $visitor->hasAdminPermission('node');

        return $perms;
    }

    abstract protected function getNameSingular();
    abstract protected function getRoutePrefix();
}
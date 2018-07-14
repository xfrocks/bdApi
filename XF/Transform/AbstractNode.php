<?php

namespace Xfrocks\Api\XF\Transform;

use XF\Entity\Node;
use Xfrocks\Api\Transform\AbstractHandler;

abstract class AbstractNode extends AbstractHandler
{
    const LINK_SUB_CATEGORIES = 'sub-categories';
    const LINK_SUB_FORUMS = 'sub-forums';

    public function getMappings($context)
    {
        return [
            'node_id' => $this->getNameSingular() . '_id',
            'title' => $this->getNameSingular() . '_title',
            'description' => $this->getNameSingular() . '_description'
        ];
    }

    public function collectLinks($context)
    {
        /** @var \XF\Entity\AbstractNode $node */
        $node = $context->getSource();

        $links = [
            self::LINK_PERMALINK => $this->buildApiLink($this->getRoutePrefix(), $node),
            self::LINK_DETAIL => $this->buildApiLink($this->getRoutePrefix(), $node),
        ];

        $nodeNode = $node->Node;
        if ($nodeNode->rgt - $nodeNode->lft > 1) {
            $linkParams = ['parent_node_id' => $node->node_id];
            $links += [
                self::LINK_SUB_CATEGORIES => $this->buildApiLink('categories', null, $linkParams),
                self::LINK_SUB_FORUMS => $this->buildApiLink('forums', null, $linkParams)
            ];
        }

        return $links;
    }

    public function collectPermissions($context)
    {
        $perms = [];

        $node = $context->getSource();
        $canView = [$node, 'canView'];
        if (is_callable($canView)) {
            $perms[self::PERM_VIEW] = call_user_func($canView);
        }

        $visitor = \XF::visitor();
        $perms[self::PERM_EDIT] = $visitor->hasAdminPermission('node');
        $perms[self::PERM_DELETE] = $visitor->hasAdminPermission('node');

        return $perms;
    }

    abstract protected function getNameSingular();

    abstract protected function getRoutePrefix();
}

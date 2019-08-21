<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\XF\Repository\Node;
use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

abstract class AbstractNode extends AbstractHandler
{
    const LINK_SUB_CATEGORIES = 'sub-categories';
    const LINK_SUB_FORUMS = 'sub-forums';

    public function getMappings(TransformContext $context)
    {
        $mappings = [
            'node_id' => $this->getNameSingular() . '_id',
            'title' => $this->getNameSingular() . '_title',
            'description' => $this->getNameSingular() . '_description'
        ];

        $this->nodeRepo()->apiTransformGetMappings($context, $mappings);

        return $mappings;
    }

    public function collectLinks(TransformContext $context)
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

        $this->nodeRepo()->apiTransformCollectLinks($context, $links);

        return $links;
    }

    public function collectPermissions(TransformContext $context)
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

        $this->nodeRepo()->apiTransformCollectPermissions($context, $perms);

        return $perms;
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        $value = $this->nodeRepo()->apiTransformCalculateDynamicValue($context, $key);
        return $value !== null ? $value : parent::calculateDynamicValue($context, $key);
    }

    /**
     * @return Node
     */
    protected function nodeRepo()
    {
        /** @var Node $nodeRepo */
        $nodeRepo = $this->app->repository('XF:Node');
        return $nodeRepo;
    }

    /**
     * @return string
     */
    abstract protected function getNameSingular();

    /**
     * @return string
     */
    abstract protected function getRoutePrefix();
}

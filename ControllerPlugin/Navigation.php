<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Transform\TransformContext;

class Navigation extends AbstractPlugin
{
    /**
     * @param \XF\Entity\Node[] $nodes
     * @return array
     */
    public function prepareElements($nodes)
    {
        /** @var AbstractController $controller */
        $controller = $this->controller;
        $context = $controller->params()->getTransformContext();
        $context->onTransformedCallbacks[] = function ($context, &$data) use ($controller) {
            /** @var TransformContext $context */
            $source = $context->getSource();
            if (!($source instanceof \XF\Entity\AbstractNode)) {
                return;
            }

            $node = $source->Node;
            if ($node === null) {
                return;
            }

            $data['navigation_type'] = strtolower($node->node_type_id);
            $data['navigation_id'] = $source->node_id;
            $data['navigation_parent_id'] = $node->parent_node_id;

            $data['has_sub_elements'] = $node->hasChildren();
            if ($data['has_sub_elements'] === true) {
                if (!isset($data['links'])) {
                    $data['links'] = [];
                }

                $data['links']['sub-elements'] = $controller->buildApiLink(
                    'navigation',
                    null,
                    ['parent' => $data['navigation_id']]
                );
            }
        };

        $elements = [];
        foreach ($nodes as $node) {
            $element = null;

            switch ($node->node_type_id) {
                case 'Category':
                    /** @var \XF\Entity\Category|null $category */
                    $category = $this->em()->instantiateEntity(
                        'XF:Category',
                        ['node_id' => $node->node_id],
                        ['Node' => $node]
                    );

                    if ($category !== null) {
                        $element = $controller->transformEntityLazily($category);
                    }
                    break;
                case 'Forum':
                    /** @var \XF\Entity\Forum|null $forum */
                    $forum = $this->em()->instantiateEntity(
                        'XF:Forum',
                        ['node_id' => $node->node_id],
                        ['Node' => $node]
                    );

                    if ($forum !== null) {
                        $element = $controller->transformEntityLazily($forum);
                    }
                    break;
                case 'LinkForum':
                    /** @var \XF\Entity\LinkForum|null $linkForum */
                    $linkForum = $this->em()->instantiateEntity(
                        'XF:LinkForum',
                        ['node_id' => $node->node_id],
                        ['Node' => $node]
                    );

                    if ($linkForum !== null) {
                        $element = $controller->transformEntityLazily($linkForum);
                    }
                    break;
                case 'Page':
                    /** @var \XF\Entity\Page|null $page */
                    $page = $this->em()->instantiateEntity(
                        'XF:Page',
                        ['node_id' => $node->node_id],
                        ['Node' => $node]
                    );

                    if ($page !== null) {
                        $element = $controller->transformEntityLazily($page);
                    }
                    break;
            }

            if ($element !== null) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @param int[] $nodeIds
     * @return array
     */
    public function prepareElementsFromIds($nodeIds)
    {
        $this->em()->findByIds('XF:Node', $nodeIds);

        $nodes = [];
        foreach ($nodeIds as $nodeId) {
            /** @var \XF\Entity\Node $node */
            $node = $this->em()->instantiateEntity('XF:Node', ['node_id' => $nodeId]);
            $nodes[] = $node;
        }

        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = $this->repository('XF:Node');
        $nodeRepo->loadNodeTypeDataForNodes($nodes);

        return $this->prepareElements($nodes);
    }
}

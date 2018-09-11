<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\LinkForum;
use XF\Entity\Node;
use XF\Tree;

class Navigation extends AbstractController
{
    public function actionGetIndex()
    {
        $params = $this
            ->params()
            ->define('parent', 'str');

        $elements = $this->getElements($params['parent']);

        $data = [
            'elements' => $elements
        ];

        return $this->api($data);
    }

    protected function getElements($parent)
    {
        if (is_numeric($parent)) {
            if ($parent > 0) {
                /** @var Node $parentNode */
                $parentNode = $this->assertRecordExists('XF:Node', $parent, [], 'bdapi_navigation_element_not_found');
                $expectedParentNodeId = $parentNode->node_id;
            } else {
                $parentNode = null;
                $expectedParentNodeId = 0;
            }
        } else {
            $parentNode = null;
            $expectedParentNodeId = null;
        }

        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = $this->repository('XF:Node');
        $nodeList = $nodeRepo->getNodeList($parentNode);

        $elements = [];

        $tree = new Tree($nodeList, 'parent_node_id');
        $forumIds = [];
        $forums = null;

        foreach ($tree->getFlattened(0) as $item) {
            if ($item['record']->node_type_id == 'Forum') {
                $forumIds[] = $item['record']->node_id;
            }
        }

        if (!empty($forumIds)) {
            $forums = $this->em()->findByIds('XF:Forum', $forumIds);
        }

        $arrangeOptions = [
            'expectedParentNodeId' => $expectedParentNodeId,
            'forums' => $forums
        ];

        $this->arrangeElements(
            $elements,
            $tree,
            is_int($expectedParentNodeId) ? $expectedParentNodeId : 0,
            $arrangeOptions
        );

        return $elements;
    }

    protected function arrangeElements(
        array &$elements,
        Tree $tree,
        $parentNodeId,
        array &$options = []
    ) {
        $this->params()->getTransformContext()->onTransformedCallbacks[] = function ($context, &$data) use ($tree) {
            $source = $context->getSource();
            if (!($source instanceof \XF\Entity\AbstractNode)) {
                return;
            }

            $data['navigation_type'] = strtolower($source->Node->node_type_id);
            $data['navigation_id'] = $source->node_id;
            $data['navigation_parent_id'] = $source->Node->parent_node_id;

            $data['has_sub_elements'] = count($tree->children($source->node_id)) > 0;
            if ($data['has_sub_elements']) {
                if (empty($data['links'])) {
                    $data['links'] = [];
                }

                $data['links']['sub-elements'] = $this->buildApiLink(
                    'navigation',
                    null,
                    ['parent' => $data['navigation_id']]
                );
            }
        };

        foreach ($tree->getFlattened(0, $parentNodeId) as $item) {
            $element = null;

            /** @var Node $node */
            $node = $item['record'];

            switch ($node->node_type_id) {
                case 'Category':
                    /** @var \XF\Entity\Category|null $category */
                    $category = $this->em()->instantiateEntity(
                        'XF:Category',
                        [
                            'node_id' => $node->node_id
                        ],
                        [
                            'Node' => $node
                        ]
                    );

                    if ($category) {
                        $element = $this->transformEntityLazily($category);
                    }
                    break;
                case 'Forum':
                    if (!empty($options['forums'][$node->node_id])) {
                        $element = $this->transformEntityLazily($options['forums'][$node->node_id]);
                    }
                    break;
                case 'LinkForum':
                    /** @var LinkForum|null $linkForum */
                    $linkForum = $this->em()->instantiateEntity(
                        'XF:LinkForum',
                        [
                            'node_id' => $node->node_id
                        ],
                        [
                            'Node' => $node
                        ]
                    );

                    if ($linkForum) {
                        $element = $this->transformEntityLazily($linkForum);
                    }
                    break;
                case 'Page':
                    /** @var \XF\Entity\Page|null $page */
                    $page = $this->em()->instantiateEntity(
                        'XF:Page',
                        [
                            'node_id' => $node->node_id
                        ],
                        [
                            'Node' => $node
                        ]
                    );

                    if ($page) {
                        $element = $this->transformEntityLazily($page);
                    }
                    break;
            }

            if (!empty($element)) {
                $elements[] = $element;
            }
        }
    }
}

<?php

namespace Xfrocks\Api\Controller;

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

        foreach ($tree as $subTree) {
            if ($subTree->record->node_type_id == 'Forum') {
                $forumIds[] = $subTree->id;
            }

            foreach ($subTree as $record) {
                if ($record->record->node_type_id == 'Forum') {
                    $forumIds[] = $record->id;
                }
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
        $this->params()->getTransformContext()->onTransformedCallbacks[] = function($context, &$data) use($elements, $tree, $options) {
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

                if ($data['has_sub_elements'] && !is_int($options['expectedParentNodeId'])) {
                    $this->arrangeElements($elements, $tree, $source->node_id, $options);
                }
            }
        };

        foreach ($tree->children($parentNodeId) as $node) {
            $element = null;

            switch ($node->record->node_type_id) {
                case 'Category':
                    $category = $this->em()->instantiateEntity(
                        'XF:Category',
                        [
                            'node_id' => $node->id
                        ],
                        [
                            'Node' => $node->record
                        ]
                    );

                    $element = $this->transformEntityLazily($category);
                    break;
                case 'Forum':
                    if (!empty($options['forums'][$node->id])) {
                        $element = $this->transformEntityLazily($options['forums'][$node->id]);
                    }
                    break;
                case 'LinkForum':
                    $category = $this->em()->instantiateEntity(
                        'XF:LinkForum',
                        [
                            'node_id' => $node->id
                        ],
                        [
                            'Node' => $node->record
                        ]
                    );

                    $element = $this->transformEntityLazily($category);
                    break;
                case 'Page':
                    $category = $this->em()->instantiateEntity(
                        'XF:Page',
                        [
                            'node_id' => $node->id
                        ],
                        [
                            'Node' => $node->record
                        ]
                    );

                    $element = $this->transformEntityLazily($category);
                    break;
            }

            if (!empty($element)) {
                $elements[] = $element;
            }
        }
    }
}
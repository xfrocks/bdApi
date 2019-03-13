<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Repository\Node;

abstract class AbstractNode extends AbstractController
{
    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->node_id) {
            return $this->actionSingle($params->node_id);
        }

        $params = $this
            ->params()
            ->define('parent_category_id', 'str', 'id of parent category')
            ->define('parent_forum_id', 'str', 'id of parent forum')
            ->define('parent_node_id', 'str', 'id of parent node')
            ->defineOrder([
                'list' => ['lft', 'asc']
            ]);

        $parentId = $params['parent_category_id'];
        if ($parentId === '') {
            $parentId = $params['parent_forum_id'];
        }
        if ($parentId === '') {
            $parentId = $params['parent_node_id'];
        }
        if ($parentId === '') {
            $parentId = false;
        } else {
            $parentId = intval($parentId);
        }

        /** @var Node $nodeRepo */
        $nodeRepo = $this->repository('XF:Node');
        $nodeList = $nodeRepo->getNodeList();

        $nodeIds = [];
        /** @var \XF\Entity\Node $nodeItem */
        foreach ($nodeList as $nodeItem) {
            if ($parentId !== false && $nodeItem->parent_node_id !== $parentId) {
                continue;
            }

            if ($nodeItem->node_type_id === $this->getNodeTypeId()) {
                $nodeIds[] = $nodeItem->node_id;
            }
        }

        $nodeTypes = $this->app()->container('nodeTypes');
        $keyNodes = $this->getNamePlural();
        $keyTotal = $this->getNamePlural() . '_total';
        $data = [$keyNodes => [], $keyTotal => 0];

        if (count($nodeIds) > 0 && isset($nodeTypes[$this->getNodeTypeId()])) {
            $entityIdentifier = $nodeTypes[$this->getNodeTypeId()]['entity_identifier'];

            $finder = $this->finder($entityIdentifier)
                ->where('node_id', $nodeIds);

            $params->sortFinder($finder);

            $data[$keyTotal] = $finder->total();
            $data[$keyNodes] = $this->transformFinderLazily($finder);
        }

        return $this->api($data);
    }

    /**
     * @param int $nodeId
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionSingle($nodeId)
    {
        $nodeTypes = $this->app()->container('nodeTypes');
        $nodeTypeId = $this->getNodeTypeId();

        if (!isset($nodeTypes[$nodeTypeId])) {
            return $this->noPermission();
        }

        $node = $this->assertRecordExists($nodeTypes[$nodeTypeId]['entity_identifier'], $nodeId);

        $data = [
            $this->getNameSingular() => $this->transformEntityLazily($node)
        ];

        return $this->api($data);
    }

    /**
     * @return string
     */
    abstract protected function getNodeTypeId();

    /**
     * @return string
     */
    abstract protected function getNamePlural();

    /**
     * @return string
     */
    abstract protected function getNameSingular();
}

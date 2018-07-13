<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Repository\Node;

abstract class AbstractNode extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->node_id) {
            return $this->actionSingle($params->node_id);
        }

        $params = $this
            ->params()
            ->define('parent_category_id', 'str', 'id of parent category')
            ->define('parent_forum_id', 'str', 'id of parent forum')
            ->defineOrder([
                'natural' => [],
                'list' => []
            ]);

        $parentId = $params['parent_category_id'];
        if ($parentId === '') {
            $parentId = $params['parent_forum_id'];
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
        $nodes = [];

        if ($nodeIds && isset($nodeTypes[$this->getNodeTypeId()])) {
            $entityIdentifier = $nodeTypes[$this->getNodeTypeId()]['entity_identifier'];
            /** @var \XF\Entity\Node[] $nodes */
            $nodes = $this->em()->findByIds($entityIdentifier, $nodeIds);
        }

        switch ($params['order']) {
            case 'list':
                usort($nodes, function ($a, $b) {
                    return (($a->lft == $b->lft) ? 0 : ($a->lft < $b->lft ? -1 : 1));
                });

                break;
        }

        $data = [
            $this->getNamePlural() => $this->transformEntitiesLazily($nodes),
            $this->getNamePlural() . '_total' => count($nodes)
        ];

        return $this->api($data);
    }

    public function actionSingle($nodeId)
    {
        $nodeTypes = $this->app()->container('nodeTypes');
        $nodeTypeId = $this->getNodeTypeId();

        if (!isset($nodeTypes[$nodeTypeId])) {
            return $this->noPermission();
        }

        $node = $this->assertViewableEntity(
            $nodeTypes[$nodeTypeId]['entity_identifier'],
            $nodeId
        );

        $data = [
            $this->getNameSingular() => $this->transformEntityLazily($node)
        ];

        return $this->api($data);
    }

    abstract protected function getNodeTypeId();
    abstract protected function getNamePlural();
    abstract protected function getNameSingular();
}

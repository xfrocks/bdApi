<?php

namespace Xfrocks\Api\Controller;

class Navigation extends AbstractController
{
    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetIndex()
    {
        $params = $this
            ->params()
            ->define('parent', 'str');

        $elements = $this->getElements($params['parent']);
        return $this->api(['elements' => $elements]);
    }

    /**
     * @param string $parentNodeId
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function getElements($parentNodeId)
    {
        $parentNode = null;
        $expectParentNodeId = null;
        if (is_numeric($parentNodeId)) {
            $expectParentNodeId = intval($parentNodeId);
            /** @var \XF\Entity\Node|null $parentNode */
            $parentNode = $expectParentNodeId > 0 ? $this->assertRecordExists(
                'XF:Node',
                $parentNodeId,
                [],
                'bdapi_navigation_element_not_found'
            ) : null;
        }

        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = $this->repository('XF:Node');
        $nodeList = $nodeRepo->getNodeList($parentNode);

        $nodes = [];
        /** @var \XF\Entity\Node $node */
        foreach ($nodeList as $node) {
            if ($expectParentNodeId !== null && $node->parent_node_id !== $expectParentNodeId) {
                continue;
            }
            $nodes[] = $node;
        }

        /** @var \Xfrocks\Api\ControllerPlugin\Navigation $navigationPlugin */
        $navigationPlugin = $this->plugin('Xfrocks\Api:Navigation');
        return $navigationPlugin->prepareElements($nodes);
    }
}

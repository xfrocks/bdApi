<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Controller\AbstractController;

class Search extends AbstractPlugin
{
    public function prepareSearchResults(array $results)
    {
        $grouped = [];
        $resultKeyMap = [];

        foreach ($results as $resultKey => $result) {
            $grouped[$result[0]][] = $result[1];

            $dataKey = $result[0] . '-' . $result[1];
            $resultKeyMap[$dataKey] = $resultKey;
        }

        $searcher = $this->app->search();
        $controller = $this->controller;

        if (!($controller instanceof AbstractController)) {
            throw new \LogicException(sprintf(
                'Controller (%s) must be instanced of (%s)',
                get_class($controller),
                'Xfrocks\Api\Controller\AbstractController'
            ));
        }

        $values = [];
        foreach ($grouped as $contentType => $contents) {
            $typeHandler = $searcher->handler(strval($contentType));
            $entities = $typeHandler->getContent(array_values($contents), true);

            /** @var Entity $entity */
            foreach ($entities as $entity) {
                $dataKey = $contentType . '-' . $entity->getEntityId();
                if (!isset($resultKeyMap[$dataKey])) {
                    continue;
                }

                $values[$resultKeyMap[$dataKey]] = $controller->transformEntityLazily($entity);
            }
        }

        $data = [];
        foreach ($results as $resultKey => $result) {
            if (isset($values[$resultKey])) {
                $data[] = $values[$resultKey];
            }
        }

        return $data;
    }
}

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
        foreach ($results as $id => $result) {
            $grouped[$result[0]][$id] = $result[1];
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
                if (!isset($results[$dataKey])) {
                    continue;
                }

                $values[$dataKey] = $controller->transformEntityLazily($entity);
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

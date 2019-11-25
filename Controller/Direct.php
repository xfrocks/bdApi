<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\Transformer;

class Direct extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        $paramType = $params->type;
        $params = $this->params()
            ->define('where', 'array');

        $types = explode('/', $paramType);
        $type0 = array_shift($types);
        $subTypes = $types;
        unset($types);

        $rootFinder = $this->finder($type0);

        $where = $params['where'];
        $input = $this->request->getInput();
        foreach ($input as $inputKey => $inputValue) {
            if (empty($inputValue)) {
                continue;
            }
            if (isset($params[$inputKey])) {
                continue;
            }
            $where[] = [$inputKey, '=', $inputValue];
        }
        foreach ($where as $whereOne) {
            if (!is_array($whereOne)) {
                continue;
            }
            try {
                call_user_func_array([$rootFinder, 'where'], array_values($whereOne));
            } catch (\InvalidArgumentException $e) {
                // bad condition, ignore
            }
        }

        $transformer = $this->transformFinderLazily($rootFinder);
        $rootContext = $params->getTransformContext();

        if (count($subTypes) > 0) {
            $rootContext->onTransformFinderCallbacks[] = function ($context, $finder) use (
                $rootContext,
                $rootFinder,
                $subTypes
            ) {
                if ($context !== $rootContext || $finder !== $rootFinder) {
                    return;
                }

                /** @var TransformContext $context */
                /** @var \XF\Mvc\Entity\Finder $finder */
                /** @var Transformer $transformer */
                $transformer = $this->app->container('api.transformer');
                $subContext = $context;
                $subFinder = $finder;
                foreach ($subTypes as $subType) {
                    try {
                        $subFinder->with($subType);
                    } catch (\LogicException $e) {
                        // bad relation key, return asap
                        return;
                    }

                    $entityStructure = $subFinder->getStructure();
                    $relationConfig = $entityStructure->relations[$subType];
                    $subHandler = $transformer->handler($relationConfig['entity']);

                    $em = $this->app->em();
                    $parentFinder = $subFinder;
                    $subFinder = $em->getFinder($relationConfig['entity'], false);
                    $subFinder->setParentFinder($parentFinder, $subType);

                    $subContext = $subContext->getSubContext(null, $subHandler);
                    $subHandler->onTransformFinder($subContext, $subFinder);
                }
            };

            $transformer->addCallbackFinderPostFetch(function ($entities) use ($subTypes) {
                $subEntities = [];

                /** @var \XF\Mvc\Entity\Entity $entity */
                foreach ($entities as $entity) {
                    if (!$this->canView($entity)) {
                        continue;
                    }

                    foreach ($subTypes as $subType) {
                        try {
                            $entity = $entity->getRelation($subType);
                        } catch (\InvalidArgumentException $e) {
                            // bad relation key, return asap
                            return $subEntities;
                        }
                        if (!$this->canView($entity)) {
                            continue;
                        }
                    }

                    $subEntities[] = $entity;
                }

                return $subEntities;
            });
        }

        $data = [$paramType => $transformer];

        return $this->api($data);
    }

    /**
     * @param \XF\Mvc\Entity\Entity $entity
     * @return bool|mixed
     */
    protected function canView(\XF\Mvc\Entity\Entity $entity)
    {
        $callable = [$entity, 'canView'];
        if (!is_callable($callable)) {
            return false;
        }

        return call_user_func($callable);
    }
}

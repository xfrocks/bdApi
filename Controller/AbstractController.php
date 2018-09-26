<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\RouteMatch;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transform\LazyTransformer;
use Xfrocks\Api\Transform\TransformContext;

class AbstractController extends \XF\Pub\Controller\AbstractController
{
    /**
     * @var Params|null
     */
    protected $apiParams = null;

    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionOptionsGeneric(ParameterBag $params)
    {
        $data = [
            'action' => $params->get('action'),
            'class' => get_class($this),
        ];

        return $this->api($data);
    }

    /**
     * @param array $data
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function api(array $data)
    {
        foreach (array_keys($data) as $key) {
            if (is_object($data[$key]) && $data[$key] instanceof LazyTransformer) {
                /** @var LazyTransformer $lazyTransformer */
                $lazyTransformer = $data[$key];
                $data[$key] = $lazyTransformer->transform();
            }
        }

        return new \Xfrocks\Api\Mvc\Reply\Api($data);
    }

    /**
     * @param string|null $scope
     * @throws \XF\Mvc\Reply\Exception
     */
    public function assertApiScope($scope)
    {
        if (empty($scope)) {
            return;
        }

        $session = $this->session();
        if (!$session->hasScope($scope)) {
            throw $this->errorException(\XF::phrase('do_not_have_permission'), 403);
        }
    }

    public function assertCanonicalUrl($linkUrl)
    {
        $responseType = $this->responseType;
        $this->responseType = 'html';
        $exception = null;

        try {
            parent::assertCanonicalUrl($linkUrl);
        } catch (\XF\Mvc\Reply\Exception $exceptionReply) {
            $reply = $exceptionReply->getReply();
            if ($reply instanceof Redirect) {
                /** @var Redirect $redirect */
                $redirect = $reply;
                $url = $redirect->getUrl();
                if (preg_match('#^https?://.+(https?://.+)$#', $url, $matches)) {
                    // because we are unable to modify XF\Http\Request::getBaseUrl,
                    // parent::assertCanonicalUrl will prepend the full base path incorrectly.
                    // And because we don't want to parse the request params ourselves
                    // we will take care of the extraneous prefix here
                    $alteredUrl = $matches[1];

                    if ($alteredUrl === $this->request->getRequestUri()) {
                        // skip redirecting, if it happens to be the current request URI
                        $exceptionReply = null;
                    } else {
                        $redirect->setUrl($alteredUrl);
                    }
                }
            }

            $exception = $exceptionReply;
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->responseType = $responseType;
        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * @param string $link
     * @param mixed $data
     * @param array $parameters
     * @return string
     */
    public function buildApiLink($link, $data = null, array $parameters = [])
    {
        return $this->app->router('api')->buildLink($link, $data, $parameters);
    }

    public function checkCsrfIfNeeded($action, ParameterBag $params)
    {
        // no op
    }

    public function filter($key, $type = null, $default = null)
    {
        throw new \InvalidArgumentException('AbstractController::params() must be used to parse params.');
    }

    /**
     * @param string $type
     * @param array|int $whereId
     * @return LazyTransformer
     */
    public function findAndTransformLazily($type, $whereId)
    {
        $finder = $this->finder($type);
        $finder->whereId($whereId);

        $sortByList = null;
        $isSingle = true;
        if (is_array($whereId)) {
            $primaryKey = $finder->getStructure()->primaryKey;
            if (is_array($primaryKey) && count($primaryKey) === 1) {
                $primaryKey = reset($primaryKey);
            }
            if (!is_array($primaryKey)) {
                $isSingle = false;
                $sortByList = $whereId;
            } else {
                // TODO: implement this
                throw new \RuntimeException('Compound primary key is not supported');
            }
        }

        $lazyTransformer = new LazyTransformer($this);
        $lazyTransformer->setFinder($finder);

        if ($sortByList !== null) {
            $lazyTransformer->addCallbackFinderPostFetch(function ($entities) use ($sortByList) {
                /** @var \XF\Mvc\Entity\ArrayCollection $arrayCollection */
                $arrayCollection = $entities;
                $entities = $arrayCollection->sortByList($sortByList);

                return $entities;
            });
        }

        $lazyTransformer->addCallbackPostTransform(function ($data) use ($isSingle) {
            if (!$isSingle) {
                return $data;
            }

            if (count($data) === 1) {
                return $data[0];
            }

            throw $this->exception($this->notFound());
        });

        return $lazyTransformer;
    }

    /**
     * @return Params
     */
    public function params()
    {
        if ($this->apiParams === null) {
            $this->apiParams = new Params($this);
        }

        return $this->apiParams;
    }

    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $this->apiParams = null;

        $addOnId = 'Xfrocks/Api';
        $addOnCache = $this->app->container('addon.cache');
        if (empty($addOnCache[$addOnId])) {
            throw $this->errorException('The API is currently disabled.', 500);
        }
        if (\XF::$debugMode) {
            $addOn = $this->app->addOnManager()->getById($addOnId);
            if ($addOn->isJsonVersionNewer()) {
                throw $this->errorException('Please update the API add-on.', 500);
            }
        }

        $scope = $this->getDefaultApiScopeForAction($action);
        $this->assertApiScope($scope);
    }

    public function reroute(RouteMatch $match)
    {
        $match->setParam('_isApiReroute', true);
        return parent::reroute($match);
    }

    /**
     * @return \Xfrocks\Api\XF\ApiOnly\Session\Session
     */
    public function session()
    {
        /** @var \Xfrocks\Api\XF\ApiOnly\Session\Session $session */
        $session = parent::session();
        return $session;
    }

    /**
     * @param array $data
     * @param string $key
     * @param Entity $entity
     * @return LazyTransformer
     */
    public function transformEntityIfNeeded(array &$data, $key, $entity)
    {
        $lazyTransformer = $this->transformEntityLazily($entity);
        $lazyTransformer->addCallbackPreTransform(function ($context) use ($key) {
            /** @var TransformContext $context */
            if ($context->selectorShouldExcludeField($key)) {
                return null;
            }

            return $context->getSubContext($key, null, null);
        });
        $data[$key] = $lazyTransformer;

        return $lazyTransformer;
    }

    /**
     * @param Entity $entity
     * @return LazyTransformer
     */
    public function transformEntityLazily($entity)
    {
        $lazyTransformer = new LazyTransformer($this);
        $lazyTransformer->setEntity($entity);
        return $lazyTransformer;
    }

    /**
     * @param Finder $finder
     * @return LazyTransformer
     */
    public function transformFinderLazily($finder)
    {
        $lazyTransformer = new LazyTransformer($this);
        $lazyTransformer->setFinder($finder);
        return $lazyTransformer;
    }

    public function view($viewClass = '', $templateName = '', array $params = [])
    {
        if (!empty($viewClass)) {
            $viewClass = \XF::stringToClass($viewClass, '%s\%s\View\%s', 'Pub');
        }

        return parent::view($viewClass, $templateName, $params);
    }

    protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
    {
        return false;
    }

    /**
     * @param string $action
     * @return string|null
     */
    protected function getDefaultApiScopeForAction($action)
    {
        if (strpos($action, 'Post') === 0) {
            return Server::SCOPE_POST;
        } elseif (strpos($action, 'Put') === 0) {
            // TODO: separate scope?
            return Server::SCOPE_POST;
        } elseif (strpos($action, 'Delete') === 0) {
            // TODO: separate scope?
            return Server::SCOPE_POST;
        } elseif ($this->options()->bdApi_restrictAccess) {
            return Server::SCOPE_READ;
        }

        return null;
    }
}

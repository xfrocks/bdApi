<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect;
use Xfrocks\Api\Data\Param;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\Util\LazyTransformer;

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

    public function finder($type)
    {
        /** @var Transformer $transformer */
        $transformer = $this->app->container('api.transformer');
        $handler = $transformer->handler($type);
        $with = $handler->getFetchWith();

        return parent::finder($type)->with($with);
    }

    /**
     * @param string $key
     * @param string|null $type
     * @param string|null $description
     * @return Param
     */
    public function param($key, $type = null, $description = null)
    {
        return new Param($key, $type, $description);
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

    /**
     * @return \Xfrocks\Api\XF\Session\Session
     */
    public function session()
    {
        /** @var \Xfrocks\Api\XF\Session\Session $session */
        $session = parent::session();
        return $session;
    }

    /**
     * @param array $data
     * @param string $key
     * @param Entity $entity
     */
    public function transformEntityIfNeeded(array &$data, $key, $entity)
    {
        $lazyTransformer = $this->transformEntityLazily($entity);
        $lazyTransformer->setKey($key);
        $data[$key] = $lazyTransformer;
    }

    /**
     * @param Entity[] $entities
     * @return LazyTransformer
     */
    public function transformEntitiesLazily($entities)
    {
        $lazyTransformer = new LazyTransformer($this);
        $lazyTransformer->setEntities($entities);
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

    public function view($viewClass = '', $templateName = '', array $params = [])
    {
        if (!empty($viewClass)) {
            $viewClass = \XF::stringToClass($viewClass, '%s\%s\View\%s', 'Pub');
        }

        return parent::view($viewClass, $templateName, $params);
    }

    /**
     * @param string $shortName
     * @param mixed $id
     * @param array $extraWith
     * @return Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableEntity($shortName, $id, array $extraWith = [])
    {
        /** @var Transformer $transformer */
        $transformer = $this->app->container('api.transformer');
        $handler = $transformer->handler($shortName);
        $with = $handler->getFetchWith($extraWith);

        $entity = $this->em()->find($shortName, $id, $with);
        if (!$entity) {
            throw $this->exception($this->notFound($handler->getNotFoundMessage()));
        }

        $canView = [$entity, 'canView'];
        $error = '';
        if (is_callable($canView)) {
            $canViewArgs = [&$error];
            if (!call_user_func_array($canView, $canViewArgs)) {
                throw $this->exception($this->noPermission($error));
            }
        }

        return $entity;
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

<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use Xfrocks\Api\Mvc\Reply;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\XF\Session\Session;

class AbstractController extends \XF\Pub\Controller\AbstractController
{
    public function actionOptionsGeneric(ParameterBag $params)
    {
        $data = [
            'action' => $params->get('action'),
            'class' => get_class($this),
        ];

        return $this->api($data);
    }

    public function api(array $data)
    {
        return new Reply($data);
    }

    public function assertApiScope($scope)
    {
        if (empty($scope)) {
            return;
        }

        /** @var Session $session */
        $session = $this->session();
        if (!$session->hasScope($scope)) {
            throw $this->errorException(\XF::phrase('do_not_have_permission'), 403);
        }
    }

    public function assertCanonicalUrl($linkUrl)
    {
        try {
            parent::assertCanonicalUrl($linkUrl);
        } catch (Exception $e) {
            $reply = $e->getReply();
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
                        return;
                    }

                    $redirect->setUrl($alteredUrl);
                }
            }

            throw $e;
        }
    }

    public function buildApiLink($link, $data = null, array $parameters = [])
    {
        return $this->app->router('api')->buildLink($link, $data, $parameters);
    }

    public function checkCsrfIfNeeded($action, ParameterBag $params)
    {
        // no op
    }

    public function getFetchWith($shortName)
    {
        /** @var Transformer $transformer */
        $transformer = $this->app->container('api.transformer');

        return $transformer->getFetchWith($this, $shortName);
    }

    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $scope = $this->getDefaultApiScopeForAction($action);
        $this->assertApiScope($scope);
    }

    /**
     * @return Session
     */
    public function session()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::session();
    }

    /**
     * @param Entity $entity
     * @return array
     */
    public function transformEntity($entity)
    {
        /** @var Transformer $transformer */
        $transformer = $this->app->container('api.transformer');

        return $transformer->transformEntity($this, $entity);
    }

    public function view($viewClass = '', $templateName = '', array $params = [])
    {
        return $this->api($params);
    }

    protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
    {
        return false;
    }

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

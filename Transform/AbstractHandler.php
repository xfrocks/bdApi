<?php

namespace Xfrocks\Api\Transform;

use XF\App;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\XF\Session\Session;

abstract class AbstractHandler
{
    const KEY_LINKS = 'links';
    const KEY_PERMISSIONS = 'permissions';

    const DYNAMIC_KEY_ATTACHMENTS = 'attachments';
    const DYNAMIC_KEY_FIELDS = 'fields';

    const LINK_ATTACHMENTS = 'attachments';
    const LINK_DETAIL = 'detail';
    const LINK_FOLLOWERS = 'followers';
    const LINK_LIKES = 'likes';
    const LINK_PERMALINK = 'permalink';
    const LINK_REPORT = 'report';

    const PERM_DELETE = 'delete';
    const PERM_EDIT = 'edit';
    const PERM_FOLLOW = 'follow';
    const PERM_LIKE = 'like';
    const PERM_REPORT = 'report';
    const PERM_VIEW = 'view';

    /**
     * @var App
     */
    protected $app;

    /**
     * @var Entity
     */
    protected $entity;

    /**
     * @var AbstractHandler
     */
    protected $parent;

    /**
     * @var Selector
     */
    protected $selector;

    /**
     * @var Transformer
     */
    protected $transformer;

    /**
     * @param App $app
     * @param Transformer $transformer
     */
    public function __construct($app, $transformer)
    {
        $this->app = $app;
        $this->transformer = $transformer;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function calculateDynamicValue($key)
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function collectLinks()
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function collectPermissions()
    {
        return null;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getEntityValue($key)
    {
        return $this->entity !== null ? $this->entity->offsetGet($key) : null;
    }

    /**
     * @param array $extraWith
     * @return array
     */
    public function getFetchWith(array $extraWith = [])
    {
        return $extraWith;
    }

    /**
     * @return array
     */
    public function getMappings()
    {
        return [];
    }

    /**
     * @param string $key
     * @return Selector|null
     */
    public function getSubSelector($key)
    {
        if ($this->selector === null) {
            return null;
        }

        return $this->selector->getSubSelector($key);
    }

    /**
     * @param Entity|\XF\CustomField\Definition $entity
     * @param AbstractHandler $parent
     * @param Selector $selector
     */
    public function reset($entity, $parent, $selector)
    {
        $this->entity = $entity;
        $this->parent = $parent;
        $this->selector = $selector;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function shouldExcludeField($key)
    {
        if ($this->selector === null) {
            return false;
        }

        return $this->selector->shouldExcludeField($key);
    }

    /**
     * @param string $link
     * @param mixed $data
     * @param array $parameters
     * @return string
     */
    protected function buildApiLink($link, $data = null, array $parameters = [])
    {
        $apiRouter = $this->app->router('api');
        return $apiRouter->buildLink($link, $data, $parameters);
    }

    /**
     * @param string $link
     * @param mixed $data
     * @param array $parameters
     * @return string
     */
    protected function buildPublicLink($link, $data = null, array $parameters = [])
    {
        $publicRouter = $this->app->router('public');
        return $publicRouter->buildLink($link, $data, $parameters);
    }

    /**
     * @param string $scope
     * @return bool
     */
    protected function checkSessionScope($scope)
    {
        /** @var Session $session */
        $session = $this->app->session();
        return is_callable([$session, 'hasScope']) && $session->hasScope($scope);
    }

    /**
     * @param string $permissionId
     * @return bool
     */
    protected function checkAdminPermission($permissionId)
    {
        if (!$this->checkSessionScope(Server::SCOPE_MANAGE_SYSTEM)) {
            return false;
        }

        return \XF::visitor()->hasAdminPermission($permissionId);
    }

    /**
     * @param string $key
     * @param $string
     * @param array $options
     * @return string
     */
    protected function renderBbCodeHtml($key, $string, array $options = [])
    {
        $context = 'api:' . $key;
        $entity = $this->entity;
        return $this->app->bbCode()->render($string, 'html', $context, $entity, $options);
    }

    /**
     * @param string $string
     * @param array $options
     * @return string
     */
    protected function renderBbCodePlainText($string, array $options = [])
    {
        $formatter = $this->app->stringFormatter();
        return $formatter->stripBbCode($string, $options);
    }

    /**
     * @return \XF\Template\Templater
     */
    protected function getTemplater()
    {
        return $this->app->templater();
    }
}

<?php

namespace Xfrocks\Api\Transform;

use XF\App;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use Xfrocks\Api\Data\TransformContext;
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
     * @param TransformContext $context
     * @param string $key
     * @return mixed
     */
    public function calculateDynamicValue($context, $key)
    {
        return null;
    }

    /**
     * @param TransformContext $context
     * @return array|null
     */
    public function collectLinks($context)
    {
        return null;
    }

    /**
     * @param TransformContext $context
     * @return array|null
     */
    public function collectPermissions($context)
    {
        return null;
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
     * @param TransformContext $context
     * @return array
     */
    public function getMappings($context)
    {
        return [];
    }

    /**
     * @return \XF\Phrase|string|null
     */
    public function getNotFoundMessage()
    {
        return null;
    }

    /**
     * @param Selector|null $selector
     * @param string $key
     * @return Selector|null
     */
    public function getSubSelector($selector, $key)
    {
        if ($selector === null) {
            return null;
        }

        return $selector->getSubSelector($key);
    }

    /**
     * @param Finder $finder
     * @param Selector $selector
     * @return Finder
     */
    public function onLazyTransformBeforeFetching($finder, $selector)
    {
        return $finder;
    }

    /**
     * @param Entity[] $entities
     * @param Selector $selector
     * @return Entity[]
     */
    public function onLazyTransformEntities($entities, $selector)
    {
        return $entities;
    }

    /**
     * @param TransformContext $context
     * @return array
     */
    public function onNewContext($context)
    {
        return [];
    }

    /**
     * @param TransformContext $context
     * @param string $key
     * @return bool
     */
    public function shouldExcludeField($context, $key)
    {
        if ($context->selector === null) {
            return false;
        }

        return $context->selector->shouldExcludeField($key);
    }

    /**
     * @param TransformContext $context
     * @param string $key
     * @return bool
     */
    public function shouldIncludeField($context, $key)
    {
        if ($context->selector === null) {
            return false;
        }

        return $context->selector->shouldIncludeField($key);
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
     * @param string $contentType
     * @param Entity $entity
     * @return bool
     */
    protected function checkAttachmentCanManage($contentType, $entity)
    {
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->app->repository('XF:Attachment');
        $attachmentHandler = $attachmentRepo->getAttachmentHandler($contentType);
        if ($attachmentHandler === null) {
            return false;
        }

        $attachmentContext = $attachmentHandler->getContext($entity);
        return $attachmentHandler->canManageAttachments($attachmentContext);
    }

    /**
     * @param string $key
     * @param string $string
     * @param mixed $content
     * @param array $options
     * @return string
     */
    protected function renderBbCodeHtml($key, $string, $content, array $options = [])
    {
        $string = utf8_trim($string);
        if (strlen($string) === 0) {
            return '';
        }

        $context = 'api:' . $key;
        return $this->app->bbCode()->render($string, 'html', $context, $content, $options);
    }

    /**
     * @param string $string
     * @param array $options
     * @return string
     */
    protected function renderBbCodePlainText($string, array $options = [])
    {
        $string = utf8_trim($string);
        if (strlen($string) === 0) {
            return '';
        }

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

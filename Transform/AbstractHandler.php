<?php

namespace Xfrocks\Api\Transform;

use XF\App;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\XF\ApiOnly\Session\Session;

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
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected static $entitiesToAddAttachmentsTo = [];

    /**
     * @param App $app
     * @param Transformer $transformer
     * @param string $type
     */
    public function __construct($app, $transformer, $type)
    {
        $this->app = $app;
        $this->transformer = $transformer;
        $this->type = $type;
    }

    public function addAttachmentsToQueuedEntities()
    {
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->app->repository('XF:Attachment');

        foreach (self::$entitiesToAddAttachmentsTo as $q) {
            $attachmentRepo->addAttachmentsToContent(
                $q['entities'],
                $q['contentType'],
                $q['countKey'],
                $q['relationKey']
            );
        }

        self::$entitiesToAddAttachmentsTo = [];
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
     * @return bool
     */
    public function canView($context)
    {
        /** @var callable $callable */
        $callable = [$context->getSource(), 'canView'];

        // we do not check is_callable here, PHP will just complain (loudly) if it doesn't work
        return call_user_func($callable);
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
     * @param TransformContext $context
     * @return array
     */
    public function getMappings($context)
    {
        return [];
    }

    /**
     * @param TransformContext $context
     * @return array
     */
    public function onNewContext($context)
    {
        $context->makeSureSelectorIsNotNull($this->type);

        return [];
    }

    /**
     * @param TransformContext $context
     * @param Finder $finder
     * @return Finder
     */
    public function onTransformFinder($context, $finder)
    {
        foreach ($context->getOnTransformFinderCallbacks() as $callback) {
            call_user_func_array($callback, [$context, $finder]);
        }

        return $finder;
    }

    /**
     * @param TransformContext $context
     * @param Entity[] $entities
     * @return Entity[]
     */
    public function onTransformEntities($context, $entities)
    {
        foreach ($context->getOnTransformEntitiesCallbacks() as $callback) {
            call_user_func_array($callback, [$context, $entities]);
        }

        return $entities;
    }

    /**
     * @param TransformContext $context
     * @param array $data
     */
    public function onTransformed($context, array &$data)
    {
        foreach ($context->getOnTransformedCallbacks() as $callback) {
            $params = [$context, &$data];
            call_user_func_array($callback, $params);
        }
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
     * @param TransformContext $context
     * @param Entity[]|AbstractCollection $entities
     * @param string|null $contextKey
     * @param string $relationKey
     */
    protected function callOnTransformEntitiesForRelation($context, $entities, $contextKey, $relationKey)
    {
        if ($entities instanceof AbstractCollection) {
            if (!$entities->count()) {
                return;
            }

            $entity = $entities->first();
        } else {
            $entity = reset($entities);
        }

        if ($entity === false) {
            return;
        }

        $entityStructure = $entity->structure();
        if (!isset($entityStructure->relations[$relationKey])) {
            return;
        }

        $relationConfig = $entityStructure->relations[$relationKey];
        if (!is_array($relationConfig) ||
            !isset($relationConfig['type']) ||
            !isset($relationConfig['entity'])
        ) {
            return;
        }

        $subHandler = $this->transformer->handler($relationConfig['entity']);
        $subContext = $context->getSubContext($contextKey, $subHandler);

        $subEntities = [];
        foreach ($entities as $entity) {
            if ($relationConfig['type'] === Entity::TO_ONE) {
                /** @var Entity $subEntity */
                $subEntity = $entity->getRelation($relationKey);
                $subEntities[$subEntity->getEntityId()] = $subEntity;
            } else {
                /** @var Entity[] $_subEntities */
                $_subEntities = $entity->getRelation($relationKey);
                foreach ($_subEntities as $subEntity) {
                    $subEntities[$subEntity->getEntityId()] = $subEntity;
                }
            }
        }

        $subHandler->onTransformEntities($subContext, $subEntities);
    }

    /**
     * @param TransformContext $context
     * @param Finder $finder
     * @param string|null $contextKey
     * @param string $relationKey
     * @param string|null $shortName
     * @throws \Exception
     */
    protected function callOnTransformFinderForRelation($context, $finder, $contextKey, $relationKey, $shortName = null)
    {
        $finder->with($relationKey);

        if ($shortName === null) {
            $shortName = $this->type;
        }

        $em = $this->app->em();
        $entityStructure = $em->getEntityStructure($shortName);
        if (!isset($entityStructure->relations[$relationKey])) {
            return;
        }

        $relationConfig = $entityStructure->relations[$relationKey];
        if (!is_array($relationConfig) || !isset($relationConfig['entity'])) {
            return;
        }

        $subHandler = $this->transformer->handler($relationConfig['entity']);
        $subContext = $context->getSubContext($contextKey, $subHandler);

        $relationStructure = $em->getEntityStructure($relationConfig['entity']);
        $finderClass = \XF::stringToClass($shortName, '%s\Finder\%s');
        $finderClass = $this->app->extendClass($finderClass, '\XF\Mvc\Entity\Finder');
        if (!$finderClass || !class_exists($finderClass)) {
            $finderClass = '\XF\Mvc\Entity\Finder';
        }
        /** @var Finder $relationFinder */
        $relationFinder = new $finderClass($em, $relationStructure);
        $relationFinder->setParentFinder($finder, $relationKey);

        $subHandler->onTransformFinder($subContext, $relationFinder);
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
     * @param Entity[] $entities
     * @param string $contentType
     * @param string $countKey
     * @param string $relationKey
     */
    protected function enqueueEntitiesToAddAttachmentsTo(
        $entities,
        $contentType,
        $countKey = 'attach_count',
        $relationKey = 'Attachments'
    ) {
        $key = sprintf('%s_%s_%s', $contentType, $countKey, $relationKey);
        if (!isset(self::$entitiesToAddAttachmentsTo[$key])) {
            self::$entitiesToAddAttachmentsTo[$key] = [
                'entities' => [],
                'contentType' => $contentType,
                'countKey' => $countKey,
                'relationKey' => $relationKey,
            ];
        }
        $ref =& self::$entitiesToAddAttachmentsTo[$key];

        foreach ($entities as $entityId => $entity) {
            $ref['entities'][$entityId] = $entity;
        }
    }

    /**
     * @return \XF\Template\Templater
     */
    protected function getTemplater()
    {
        return $this->app->templater();
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
}

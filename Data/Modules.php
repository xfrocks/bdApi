<?php

namespace Xfrocks\Api\Data;

use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Listener;

class Modules
{
    /**
     * @var array
     */
    private $routes = [];

    /**
     * @var array
     */
    private $versions = [
        'forum' => 2018092501,
        'oauth2' => 2016030902,
        'subscription' => 2014092301,
    ];

    public function __construct()
    {
        $this->addController('Xfrocks:Asset', 'assets');
        $this->addController('Xfrocks:Attachment', 'attachments', ':int<attachment_id>/');
        $this->addController('Xfrocks:Batch', 'batch');
        $this->addController('Xfrocks:Category', 'categories', ':int<node_id>/');
        $this->addController('Xfrocks:Conversation', 'conversations', ':int<conversation_id>/');
        $this->addController('Xfrocks:ConversationMessage', 'conversation-messages', ':int<message_id>/');
        $this->addController('Xfrocks:Direct', 'direct', ':any<type>/');
        $this->addController('Xfrocks:Index', 'index');
        $this->addController('Xfrocks:Forum', 'forums', ':int<node_id>/');
        $this->addController('Xfrocks:LostPassword', 'lost-password');
        $this->addController('Xfrocks:Navigation', 'navigation');
        $this->addController('Xfrocks:Notification', 'notifications', ':int<alert_id>/');
        $this->addController('Xfrocks:OAuth2', 'oauth');
        $this->addController('Xfrocks:Page', 'pages', ':int<node_id>/');
        $this->addController('Xfrocks:ProfilePost', 'profile-posts', ':int<profile_post_id>/');
        $this->addController('Xfrocks:Post', 'posts', ':int<post_id>/');
        $this->addController('Xfrocks:Search', 'search', ':int<search_id>/');
        $this->addController('Xfrocks:Subscription', 'subscriptions', ':int<subscription_id>/');
        $this->addController('Xfrocks:Tag', 'tags', ':int<tag_id>/');
        $this->addController('Xfrocks:Thread', 'threads', ':int<thread_id>/');
        $this->addController('Xfrocks:Tool', 'tools');
        $this->addController('Xfrocks:User', 'users', ':int<user_id>/');
    }

    /**
     * @param AbstractController $controller
     * @return array
     */
    public function getDataForApiIndex($controller)
    {
        $app = $controller->app();
        $apiRouter = $app->router(Listener::$routerType);
        $visitor = \XF::visitor();
        $threadLinkParams = ['data_limit' => $app->options()->discussionsPerPage];

        $data = [
            'links' => [
                'navigation' => $apiRouter->buildLink('navigation', null, ['parent' => 0]),
                'search' => $apiRouter->buildLink('search'),
                'threads/recent' => $apiRouter->buildLink('threads/recent', null, $threadLinkParams),
                'users' => $apiRouter->buildLink('users')
            ],
            'post' => [],
        ];

        if ($visitor->user_id > 0) {
            $data['links'] += [
                'conversations' => $apiRouter->buildLink('conversations'),
                'forums/followed' => $apiRouter->buildLink('forums/followed'),
                'notifications' => $apiRouter->buildLink('notifications'),
                'threads/followed' => $apiRouter->buildLink('threads/followed'),
                'threads/new' => $apiRouter->buildLink('threads/new', null, $threadLinkParams),
                'users/ignored' => $apiRouter->buildLink('users/ignored'),
                'users/me' => $apiRouter->buildLink('users', $visitor)
            ];

            if ($visitor->canPostOnProfile()) {
                $data['post']['status'] = $apiRouter->buildLink('users/me/timeline');
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    final public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @param string $shortName
     * @return string
     */
    public function getTransformerClass($shortName)
    {
        return \XF::stringToClass($shortName, 'Xfrocks\Api\%s\Transform\%s');
    }

    /**
     * @return array
     */
    final public function getVersions()
    {
        return $this->versions;
    }

    /**
     * @param string $controller
     * @param string $prefix
     * @param string $format
     * @param null|callable $buildCallback
     * @param string $subSection
     * @param string $context
     * @param string $actionPrefix
     * @return void
     */
    protected function addController(
        $controller,
        $prefix,
        $format = '',
        $buildCallback = null,
        $subSection = '',
        $context = '',
        $actionPrefix = ''
    ) {
        $this->addRoute($prefix, $subSection, [
            'format' => $format,
            'build_callback' => $buildCallback,
            'controller' => $controller,
            'context' => $context,
            'action_prefix' => $actionPrefix
        ]);
    }

    /**
     * @param string $prefix
     * @param string $subSection
     * @param array $route
     * @return void
     */
    protected function addRoute($prefix, $subSection, $route)
    {
        if (isset($this->routes[$prefix][$subSection])) {
            throw new \InvalidArgumentException(sprintf('Route "%s"."%s" has already existed', $prefix, $subSection));
        }

        $this->routes[$prefix][$subSection] = $route;
    }

    /**
     * @param string $id
     * @param int $version
     * @return void
     */
    protected function register($id, $version)
    {
        if (isset($this->versions[$id])) {
            throw new \InvalidArgumentException(sprintf('Module "%s" has already been registered', $id));
        }

        $this->versions[$id] = $version;
    }
}

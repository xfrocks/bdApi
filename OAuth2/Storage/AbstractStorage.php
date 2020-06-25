<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\AbstractServer;
use League\OAuth2\Server\Storage\StorageInterface;
use Xfrocks\Api\App;
use Xfrocks\Api\Entity\TokenWithScope;
use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Server;

abstract class AbstractStorage implements StorageInterface
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @var AbstractServer
     */
    protected $server;

    /**
     * @var array
     */
    protected $xfEntities = [];

    /**
     * @param App $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param AbstractServer $server
     * @return void
     */
    final public function setServer(AbstractServer $server)
    {
        $this->server = $server;
    }

    /**
     * @param TokenWithScope $xfEntity
     * @return bool
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    protected function doXfEntityDelete($xfEntity)
    {
        $deleted = $xfEntity->delete(true, false);

        if ($deleted) {
            $text = $xfEntity->getText();
            if (isset($this->xfEntities[$text])) {
                unset($this->xfEntities[$text]);
            }
        }

        return $deleted;
    }

    /**
     * @param string $shortName
     * @param string $textColumn
     * @param string $text
     * @return TokenWithScope|null
     */
    protected function doXfEntityFind($shortName, $textColumn, $text)
    {
        if (isset($this->xfEntities[$text])) {
            return $this->xfEntities[$text];
        }

        $with = $this->getXfEntityWith();

        /** @var TokenWithScope|null $xfEntity */
        $xfEntity = $this->app->em()->findOne($shortName, [$textColumn => $text], $with);
        if ($xfEntity !== null) {
            $this->xfEntities[$text] = $xfEntity;
        }

        return $xfEntity;
    }

    /**
     * @param TokenWithScope $xfEntity
     * @return bool
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    protected function doXfEntitySave($xfEntity)
    {
        $saved = $xfEntity->save(true, false);

        if ($saved) {
            $text = $xfEntity->getText();
            $this->xfEntities[$text] = $xfEntity;
        }

        return $saved;
    }

    /**
     * @param array $scopes
     * @return string
     */
    protected function scopeBuildStrFromObjArray(array $scopes)
    {
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');
        $scopeIds = $apiServer->getScopeStrArrayFromObjArray($scopes);

        return implode(Listener::$scopeDelimiter, $scopeIds);
    }

    /**
     * @param array $scopes
     * @return array
     */
    protected function scopeBuildObjArrayFromStrArray(array $scopes)
    {
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');
        return $apiServer->getScopeObjArrayFromStrArray($scopes, $this->server);
    }

    /**
     * @return array
     */
    protected function getXfEntityWith()
    {
        return [];
    }
}

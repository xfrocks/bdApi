<?php

namespace Xfrocks\Api\OAuth2\Storage;

use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\ScopeInterface;
use Xfrocks\Api\OAuth2\Server;

class ScopeStorage extends AbstractStorage implements ScopeInterface
{
    public function get($scope, $grantType = null, $clientId = null)
    {
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');
        $description = $apiServer->getScopeDescription($scope);
        if ($description === null) {
            return null;
        }

        $result = new ScopeEntity($this->server);
        $result->hydrate([
            'id' => $scope,
            'description' => $description
        ]);

        return $result;
    }
}

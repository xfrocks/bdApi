<?php

namespace Xfrocks\Api\OAuth2\TokenType;

use League\OAuth2\Server\TokenType\Bearer;
use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Storage\SessionStorage;

class BearerWithScope extends Bearer
{
    public function generateResponse()
    {
        $response = parent::generateResponse();

        if (!empty($this->session)) {
            $session = $this->session;

            $scopes = $session->getScopes();
            $scopeIds = [];
            foreach ($scopes as $scope) {
                $scopeIds[] = $scope->getId();
            }
            $response['scope'] = implode(Listener::$scopeDelimiter, $scopeIds);

            if ($session->getOwnerType() === SessionStorage::OWNER_TYPE_USER) {
                $response['user_id'] = intval($session->getOwnerId());
            }
        }

        return $response;
    }
}

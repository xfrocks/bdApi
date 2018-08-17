<?php

namespace Xfrocks\Api\OAuth2\TokenType;

use League\OAuth2\Server\TokenType\Bearer;
use Xfrocks\Api\Listener;

class BearerWithScope extends Bearer
{
    public function generateResponse()
    {
        $response = parent::generateResponse();

        if (!empty($this->session)) {
            $scopes = $this->session->getScopes();
            $scopeIds = [];
            foreach ($scopes as $scope) {
                $scopeIds[] = $scope->getId();
            }
            $response['scope'] = implode(Listener::$scopeDelimiter, $scopeIds);
        }

        return $response;
    }
}

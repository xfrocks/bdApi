<?php

namespace Xfrocks\Api\OAuth2\Entity;

use League\OAuth2\Server\AbstractServer;
use League\OAuth2\Server\Entity\ClientEntity;
use Xfrocks\Api\Entity\Client;

class ClientHybrid extends ClientEntity
{
    /**
     * @var Client
     */
    protected $xfClient;

    /**
     * @param AbstractServer $server
     * @param Client $xfClient
     */
    public function __construct($server, $xfClient)
    {
        parent::__construct($server);

        $this->xfClient = $xfClient;
        $this->hydrate([
            'id' => $xfClient->client_id,
            'name' => $xfClient->name
        ]);
    }

    /**
     * @return Client
     */
    public function getXfClient()
    {
        return $this->xfClient;
    }
}

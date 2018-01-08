<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;

class Client extends Repository
{
    /**
     * @param int $userId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserClients($userId)
    {
        $finder = $this->finder('Xfrocks\Api:Client');
        $finder->where('user_id', $userId);

        return $finder;
    }

    /**
     * @param int $length
     * @return string
     */
    public function generateClientId($length = 10)
    {
        while (true) {
            $clientId = \XF::generateRandomString($length);

            if (!$this->em->find('Xfrocks\Api:Client', $clientId)) {
                return $clientId;
            }
        }

        throw new \RuntimeException('Cannot generate unique client_id');
    }

    /**
     * @param int $length
     * @return string
     */
    public function generateClientSecret($length = 15)
    {
        return \XF::generateRandomString($length);
    }

    /**
     * @param array|null $values
     * @return \Xfrocks\Api\Entity\Client
     */
    public function newClient($values = null)
    {
        /** @var \Xfrocks\Api\Entity\Client $client */
        $client = $this->em->create('Xfrocks\Api:Client');

        if (is_array($values)) {
            $client->bulkSet($values);
        }

        return $client;
    }
}

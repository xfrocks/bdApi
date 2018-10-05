<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class OttTest extends ApiTestCase
{
    /**
     * @var string
     */
    private static $accessToken = '';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $token = static::postPassword(static::dataApiClient(), static::dataUser());
        self::$accessToken = $token['access_token'];
    }

    public function testWithGuest()
    {
        $this->requestActiveOttToken(0);
    }

    public function testWithUser()
    {
        $user = $this->dataUser(3);
        $this->requestActiveOttToken($user['user_id']);
    }

    protected function requestActiveOttToken($userId)
    {
        $timestamp = time() + 5 * 30;
        $accessToken = ($userId > 0) ? self::$accessToken : __METHOD__;
        $client = $this->dataApiClient();

        $once = md5($userId . $timestamp . $accessToken . $client['client_secret']);
        $ott = sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $client['client_id']);
var_dump($accessToken . ' - ' . $ott);
        $json = $this->httpRequestJson('GET', 'index', [
            'query' => [
                'oauth_token' => $ott
            ]
        ]);

        $this->assertArrayHasKey('links', $json);
        if ($userId > 0) {
            $this->assertArrayHasKey('conversations', $json['links']);
        }
    }
}

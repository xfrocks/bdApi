<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class ZzzTest extends ApiTestCase
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

    public function testPostMultipartWithQueryParams()
    {
        $accessToken = self::$accessToken;
        $fileName = 'white.png';
        $forum = $this->dataForum();

        static::httpRequest(
            'POST',
            "threads/attachments?forum_id={$forum['node_id']}&oauth_token={$accessToken}",
            [
                'body' => [
                    'file' => fopen(__DIR__ . "/files/{$fileName}", 'r')
                ]
            ]
        );

        $this->assertEquals(200, static::httpLatestResponse()->getStatusCode());
    }

    public function testPostMultipartWithBodyParams()
    {
        $fileName = 'white.png';
        $forum = $this->dataForum();

        static::httpRequest(
            'POST',
            'threads/attachments',
            [
                'body' => [
                    'file' => fopen(__DIR__ . "/files/{$fileName}", 'r'),
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => self::$accessToken
                ]
            ]
        );

        $this->assertEquals(200, static::httpLatestResponse()->getStatusCode());
    }
}

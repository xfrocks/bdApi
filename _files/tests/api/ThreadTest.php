<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class ThreadTest extends ApiTestCase
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

    public function testPostIndex()
    {
        $forum = $this->dataForum();

        $json = static::httpRequestJson(
            'POST',
            'threads',
            [
                'body' => [
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => self::$accessToken,
                    'post_body' => str_repeat(__METHOD__ . ' ', 10),
                    'thread_title' => __METHOD__,
                ],
            ]
        );

        $this->assertArrayHasKey('thread', $json);
    }
}

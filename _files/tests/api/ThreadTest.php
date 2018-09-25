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

    public function testGetIndex()
    {
        $forum = $this->dataForum();

        $jsonThreads = $this->httpRequestJson(
            'GET',
            'threads',
            [
                'query' => [
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => self::$accessToken
                ]
            ]
        );
        $this->assertArrayHasKey('threads', $jsonThreads);

        $thread = $this->dataThread();
        $excludeFields = [
            '',
            'first_post',
            'thread_is_followed'
        ];

        foreach ($excludeFields as $excludeField) {
            $jsonThread = $this->httpRequestJson(
                'GET',
                'threads/' . $thread['thread_id'],
                [
                    'query' => [
                        'oauth_token' => self::$accessToken,
                        'exclude_field' => $excludeField ?: null
                    ]
                ]
            );

            $this->assertArrayHasKey('thread', $jsonThread);
            if ($excludeField) {
                $this->assertArrayNotHasKey($excludeField, $jsonThread);
            }
        }
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

    public function testPutIndex()
    {
        $thread = $this->dataThread();

        $json = $this->httpRequestJson(
            'PUT',
            'threads/' . $thread['thread_id'],
            [
                'body' => [
                    'post_body' => str_repeat(__METHOD__ . ' ', 10),
                    'oauth_token' => self::$accessToken
                ]
            ]
        );

        $this->assertArrayHasKey('post', $json);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    /**
     * @see ThreadAttachmentTest::postThreadsAttachments()
     */
    private function _testPostAttachments()
    {
        // intentionally left blank
    }
}

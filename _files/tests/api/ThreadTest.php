<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class ThreadTest extends ApiTestCase
{
    /**
     * @var string
     */
    private static $accessTokenBypassFloodCheck = '';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $token = static::postPassword(static::dataApiClient(), static::dataUserWithBypassFloodCheckPermission());
        static::$accessTokenBypassFloodCheck = $token['access_token'];
    }

    /**
     * @return void
     */
    public function testGetIndex()
    {
        $forum = static::dataForum();

        $jsonThreads = static::httpRequestJson(
            'GET',
            "threads?forum_id={$forum['node_id']}&oauth_token=" . static::$accessTokenBypassFloodCheck
        );
        static::assertArrayHasKey('threads', $jsonThreads);

        $thread = static::dataThread();
        $excludeFields = [
            '',
            'first_post',
            'thread_is_followed'
        ];

        foreach ($excludeFields as $excludeField) {
            $jsonThread = static::httpRequestJson(
                'GET',
                "threads/{$thread['thread_id']}?exclude_fields={$excludeField}&oauth_token=" . static::$accessTokenBypassFloodCheck
            );

            static::assertArrayHasKey('thread', $jsonThread);
            if ($excludeField !== '') {
                static::assertArrayNotHasKey($excludeField, $jsonThread);
            }
        }
    }

    /**
     * @return void
     */
    public function testPostIndex()
    {
        $forum = static::dataForum();

        $json = static::httpRequestJson(
            'POST',
            'threads',
            [
                'form_params' => [
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => static::$accessTokenBypassFloodCheck,
                    'post_body' => str_repeat(__METHOD__ . ' ', 10),
                    'thread_title' => __METHOD__,
                ],
            ]
        );

        static::assertArrayHasKey('thread', $json);
    }

    /**
     * @return void
     */
    public function testPutIndex()
    {
        $thread = static::dataThread();
        $token = $this::postPassword(static::dataApiClient(), static::dataUser());

        $json = static::httpRequestJson(
            'PUT',
            'threads/' . $thread['thread_id'],
            [
                'form_params' => [
                    'post_body' => str_repeat(__METHOD__ . ' ', 10),
                    'oauth_token' => $token['access_token']
                ]
            ]
        );

        $jsonThreadId = static::assertArrayHasKeyPath($json, 'thread', 'thread_id');
        static::assertEquals($thread['thread_id'], $jsonThreadId);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    /**
     * @return void
     * @see ThreadAttachmentTest::postThreadsAttachments()
     */
    private function _testPostAttachments()
    {
        // intentionally left blank
    }
}

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
        self::$accessTokenBypassFloodCheck = $token['access_token'];
    }

    public function testGetIndex()
    {
        $forum = $this->dataForum();

        $jsonThreads = $this->httpRequestJson(
            'GET',
            "threads?forum_id={$forum['node_id']}&oauth_token=" . self::$accessTokenBypassFloodCheck
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
                "threads/{$thread['thread_id']}?exclude_fields={$excludeField}&oauth_token=" . self::$accessTokenBypassFloodCheck
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
                'form_params' => [
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => self::$accessTokenBypassFloodCheck,
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
        $token = $this::postPassword($this->dataApiClient(), $this->dataUser());

        $json = $this->httpRequestJson(
            'PUT',
            'threads/' . $thread['thread_id'],
            [
                'form_params' => [
                    'post_body' => str_repeat(__METHOD__ . ' ', 10),
                    'oauth_token' => $token['access_token']
                ]
            ]
        );

        $jsonThreadId = $this->assertArrayHasKeyPath($json, 'thread', 'thread_id');
        $this->assertEquals($thread['thread_id'], $jsonThreadId);
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

<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class ThreadAttachmentTest extends ApiTestCase
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

    public function testUploadForExistingThread()
    {
        $thread = $this->postThreads(__METHOD__);
        $threadId = $this->assertArrayHasKeyPath($thread, 'thread_id');

        $hash = strval(microtime(true));
        $attachment = $this->postThreadsAttachments($hash);
        $attachmentId = $this->assertArrayHasKeyPath($attachment, 'attachment_id');

        $json = $this->httpRequestJson(
            'PUT',
            "threads/{$threadId}",
            [
                'body' => [
                    'attachment_hash' => $hash,
                    'oauth_token' => self::$accessToken,
                    'post_body' => __METHOD__ . ' now with attachment',
                ],
            ]
        );
        $post = $this->assertArrayHasKeyPath($json, 'post');
        $postThreadId = $this->assertArrayHasKeyPath($post, 'thread_id');
        $this->assertEquals($threadId, $postThreadId);
        $postAttachments = $this->assertArrayHasKeyPath($post, 'attachments');

        $attachmentFound = false;
        foreach ($postAttachments as $postAttachment) {
            $postAttachmentId = $this->assertArrayHasKeyPath($postAttachment, 'attachment_id');
            if ($postAttachmentId === $attachmentId) {
                $attachmentFound = true;
            }
        }
        $this->assertTrue($attachmentFound);
    }

    public function testDeleteNewlyUploadedAttachment()
    {
        $attachment = $this->postThreadsAttachments();
        $dataLink = $this->assertArrayHasKeyPath($attachment, 'links', 'data');

        $json2 = $this->httpRequestJson('DELETE', $dataLink);
        $status = $this->assertArrayHasKeyPath($json2, 'status');
        $this->assertEquals('ok', $status);
    }

    public function testDeleteAssociatedAttachment()
    {
        $hash = strval(microtime(true));
        $attachment = $this->postThreadsAttachments($hash);

        $thread = $this->postThreads(__METHOD__, $hash);
        $threadAttachments = $this->assertArrayHasKeyPath($thread, 'first_post', 'attachments');
        $this->assertCount(1, $threadAttachments);
        $threadAttachment = reset($threadAttachments);
        $this->assertEquals(
            $attachment['attachment_id'],
            $this->assertArrayHasKeyPath($threadAttachment, 'attachment_id')
        );
        $dataLink = $this->assertArrayHasKeyPath($threadAttachment, 'links', 'data');

        $json2 = $this->httpRequestJson('DELETE', $dataLink);
        $status = $this->assertArrayHasKeyPath($json2, 'status');
        $this->assertEquals('ok', $status);
    }

    private function postThreads($method, $hash = '')
    {
        $forum = $this->dataForum();
        $json = $this->httpRequestJson(
            'POST',
            'threads',
            [
                'body' => [
                    'attachment_hash' => $hash,
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => self::$accessToken,
                    'post_body' => $method,
                    'thread_title' => $method,
                ],
            ]
        );

        return $this->assertArrayHasKeyPath($json, 'thread');
    }

    private function postThreadsAttachments($hash = '')
    {
        $accessToken = self::$accessToken;
        $fileName = 'white.png';
        $forum = $this->dataForum();

        $json = $this->httpRequestJson(
            'POST',
            "threads/attachments?attachment_hash={$hash}&forum_id={$forum['node_id']}&oauth_token={$accessToken}",
            [
                'body' => [
                    'file' => fopen(__DIR__ . "/files/{$fileName}", 'r')
                ]
            ]
        );

        return $this->assertArrayHasKeyPath($json, 'attachment');
    }
}

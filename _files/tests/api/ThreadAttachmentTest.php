<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class ThreadAttachmentTest extends ApiTestCase
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
    public function testUploadForExistingThread()
    {
        $thread = $this->postThreads(__METHOD__);
        $threadId = static::assertArrayHasKeyPath($thread, 'thread_id');

        $hash = strval(microtime(true));
        $attachment = $this->postThreadsAttachments($hash);
        $attachmentId = static::assertArrayHasKeyPath($attachment, 'attachment_id');

        $json = static::httpRequestJson(
            'PUT',
            "threads/{$threadId}",
            [
                'form_params' => [
                    'attachment_hash' => $hash,
                    'oauth_token' => static::$accessTokenBypassFloodCheck,
                    'post_body' => __METHOD__ . ' now with attachment',
                ],
            ]
        );
        $thread = static::assertArrayHasKeyPath($json, 'thread');
        $jsonThreadId = static::assertArrayHasKeyPath($thread, 'thread_id');
        static::assertEquals($threadId, $jsonThreadId);
        $postAttachments = static::assertArrayHasKeyPath($thread, 'first_post', 'attachments');

        $attachmentFound = false;
        foreach ($postAttachments as $postAttachment) {
            $postAttachmentId = static::assertArrayHasKeyPath($postAttachment, 'attachment_id');
            if ($postAttachmentId === $attachmentId) {
                $attachmentFound = true;
            }
        }
        static::assertTrue($attachmentFound);
    }

    /**
     * @return void
     */
    public function testDeleteNewlyUploadedAttachment()
    {
        $attachment = $this->postThreadsAttachments();
        $dataLink = static::assertArrayHasKeyPath($attachment, 'links', 'data');

        $json2 = static::httpRequestJson('DELETE', $dataLink);
        $status = static::assertArrayHasKeyPath($json2, 'status');
        static::assertEquals('ok', $status);
    }

    /**
     * @return void
     */
    public function testDeleteAssociatedAttachment()
    {
        $hash = strval(microtime(true));
        $attachment = $this->postThreadsAttachments($hash);

        $thread = $this->postThreads(__METHOD__, $hash);
        $threadAttachments = static::assertArrayHasKeyPath($thread, 'first_post', 'attachments');
        static::assertCount(1, $threadAttachments);
        $threadAttachment = reset($threadAttachments);
        static::assertEquals(
            $attachment['attachment_id'],
            static::assertArrayHasKeyPath($threadAttachment, 'attachment_id')
        );
        $dataLink = static::assertArrayHasKeyPath($threadAttachment, 'links', 'data');

        $json2 = static::httpRequestJson('DELETE', $dataLink);
        $status = static::assertArrayHasKeyPath($json2, 'status');
        static::assertEquals('ok', $status);
    }

    /**
     * @param string $method
     * @param string $hash
     * @return mixed
     */
    private function postThreads($method, $hash = '')
    {
        $forum = static::dataForum();
        $json = static::httpRequestJson(
            'POST',
            'threads',
            [
                'form_params' => [
                    'attachment_hash' => $hash,
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => static::$accessTokenBypassFloodCheck,
                    'post_body' => $method,
                    'thread_title' => $method,
                ],
            ]
        );

        return static::assertArrayHasKeyPath($json, 'thread');
    }

    /**
     * @param string $hash
     * @return mixed
     */
    private function postThreadsAttachments($hash = '')
    {
        $accessToken = static::$accessTokenBypassFloodCheck;
        $fileName = 'white.png';
        $forum = static::dataForum();

        $json = static::httpRequestJson(
            'POST',
            "threads/attachments?attachment_hash={$hash}&forum_id={$forum['node_id']}&oauth_token={$accessToken}",
            [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen(__DIR__ . "/files/{$fileName}", 'r'),
                        'filename' => $fileName,
                    ],
                ]
            ]
        );

        return static::assertArrayHasKeyPath($json, 'attachment');
    }
}

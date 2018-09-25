<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class AttachmentTest extends ApiTestCase
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
        $hash = strval(time());
        $attachment = $this->postThreadsAttachments($hash);

        $forum = $this->dataForum();
        $json = $this->httpRequestJson(
            'POST',
            'threads',
            [
                'body' => [
                    'attachment_hash' => $hash,
                    'forum_id' => $forum['node_id'],
                    'oauth_token' => self::$accessToken,
                    'post_body' => __METHOD__,
                    'thread_title' => __METHOD__,
                ],
            ]
        );

        $firstAttachment = $this->assertArrayHasKeyPath($json, 'thread', 'first_post', 'attachments', 0);
        $firstAttachmentId = $this->assertArrayHasKeyPath($firstAttachment, 'attachment_id');
        $this->assertEquals($attachment['attachment_id'], $firstAttachmentId);
        $dataLink = $this->assertArrayHasKeyPath($firstAttachment, 'links', 'data');

        $json2 = $this->httpRequestJson('DELETE', $dataLink);
        $status = $this->assertArrayHasKeyPath($json2, 'status');
        $this->assertEquals('ok', $status);
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

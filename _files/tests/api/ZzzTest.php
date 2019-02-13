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
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen(__DIR__ . "/files/{$fileName}", 'r'),
                        'filename' => $fileName,
                    ],
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
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen(__DIR__ . "/files/{$fileName}", 'r'),
                        'filename' => $fileName,
                    ],
                    [
                        'name' => 'forum_id',
                        'contents' => $forum['node_id'],
                    ],
                    [
                        'name' => 'oauth_token',
                        'contents' => self::$accessToken,
                    ],
                ]
            ]
        );

        $this->assertEquals(200, static::httpLatestResponse()->getStatusCode());
    }

    public function testOttWithGuest()
    {
        $this->requestActiveOttToken(0);
    }

    public function testOttWithUser()
    {
        $user = $this->dataUser();
        $this->requestActiveOttToken($user['user_id']);
    }

    protected function requestActiveOttToken($userId)
    {
        $timestamp = time() + 5 * 30;
        $accessToken = ($userId > 0) ? self::$accessToken : '';
        $client = $this->dataApiClient();
        $forum = $this->dataForum();

        $once = md5($userId . $timestamp . $accessToken . $client['client_secret']);
        $ott = sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $client['client_id']);
        $forumId = ($userId > 0) ? $forum['node_id'] : 0;
        $json = $this->httpRequestJson('GET', "threads?oauth_token={$ott}&forum_id={$forumId}");

        $this->assertArrayHasKey('threads', $json);
    }
}

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
        static::$accessToken = $token['access_token'];
    }

    /**
     * @return void
     */
    public function testPostMultipartWithQueryParams()
    {
        $accessToken = static::$accessToken;
        $fileName = 'white.png';
        $forum = static::dataForum();

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

        static::assertEquals(200, static::httpLatestResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testPostMultipartWithBodyParams()
    {
        $fileName = 'white.png';
        $forum = static::dataForum();

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
                        'contents' => static::$accessToken,
                    ],
                ]
            ]
        );

        static::assertEquals(200, static::httpLatestResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testOttWithGuest()
    {
        $this->requestActiveOttToken(0);
    }

    /**
     * @return void
     */
    public function testOttWithUser()
    {
        $user = static::dataUser();
        $this->requestActiveOttToken($user['user_id']);
    }

    /**
     * @param int $userId
     * @return void
     */
    protected function requestActiveOttToken($userId)
    {
        $timestamp = time() + 5 * 30;
        $accessToken = ($userId > 0) ? static::$accessToken : '';
        $client = static::dataApiClient();
        $forum = static::dataForum();

        $once = md5($userId . $timestamp . $accessToken . $client['client_secret']);
        $ott = sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $client['client_id']);


        $forumId = ($userId > 0) ? $forum['node_id'] : 0;
        $json = static::httpRequestJson('GET', "threads?oauth_token={$ott}&forum_id={$forumId}");

        static::assertArrayHasKey('threads', $json);
    }
}

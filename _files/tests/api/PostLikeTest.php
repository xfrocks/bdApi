<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class PostLikeTest extends ApiTestCase
{
    /**
     * @var string
     */
    private static $accessToken = '';

    /**
     * @var array
     */
    private static $user = [];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$user = static::dataUser(1);

        $token = static::postPassword(static::dataApiClient(), static::$user);
        static::$accessToken = $token['access_token'];
    }

    /**
     * @return void
     */
    public function testPostLikes()
    {
        $post = static::dataPost();

        $json = static::httpRequestJson(
            'POST',
            sprintf('posts/%d/likes', $post['post_id']),
            [
                'form_params' => [
                    'oauth_token' => static::$accessToken,
                ]
            ]
        );

        $status = static::assertArrayHasKeyPath($json, 'status');
        static::assertEquals('ok', $status);

        $likes = static::httpRequestJson(
            'GET',
            sprintf('posts/%d/likes?oauth_token=%s', $post['post_id'], static::$accessToken)
        );
        $likedUserId = static::assertArrayHasKeyPath($likes, 'users', 0, 'user_id');
        static::assertEquals(static::$user['user_id'], $likedUserId);
    }

    /**
     * @return void
     */
    public function testDeleteLikes()
    {
        $post = static::dataPost();

        $json = static::httpRequestJson(
            'DELETE',
            sprintf('posts/%d/likes', $post['post_id']),
            [
                'form_params' => [
                    'oauth_token' => static::$accessToken,
                ]
            ]
        );

        $status = static::assertArrayHasKeyPath($json, 'status');
        static::assertEquals('ok', $status);

        $likes = static::httpRequestJson(
            'GET',
            sprintf('posts/%d/likes?oauth_token=%s', $post['post_id'], static::$accessToken)
        );
        $likedUsers = static::assertArrayHasKeyPath($likes, 'users');
        static::assertEquals(0, count($likedUsers));
    }
}

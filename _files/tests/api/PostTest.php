<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class PostTest extends ApiTestCase
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

    /**
     * @return void
     */
    public function testGetIndex()
    {
        $thread = static::dataThread();

        $jsonPosts = static::httpRequestJson(
            'GET',
            'posts',
            [
                'query' => [
                    'thread_id' => $thread['thread_id'],
                    'oauth_token' => self::$accessToken
                ]
            ]
        );
        static::assertArrayHasKey('posts', $jsonPosts);

        $post = static::dataPost();
        $excludeFields = [
            '',
            'signature',
            'signature_html',
            'signature_plain_text'
        ];

        foreach ($excludeFields as $excludeField) {
            $jsonPost = static::httpRequestJson(
                'GET',
                'posts/' . $post['post_id'],
                [
                    'query' => [
                        'oauth_token' => self::$accessToken,
                        'exclude_field' => $excludeField
                    ]
                ]
            );

            static::assertArrayHasKey('post', $jsonPost);
            if ($excludeField !== '') {
                static::assertArrayNotHasKey($excludeField, $jsonPost);
            }
        }
    }

    /**
     * @return void
     */
    public function testPostIndex()
    {
        $thread = static::dataThread();

        $json = static::httpRequestJson(
            'POST',
            'posts',
            [
                'body' => [
                    'oauth_token' => self::$accessToken,
                    'thread_id' => $thread['thread_id'],
                    'post_body' => str_repeat(__METHOD__ . ' ', 10)
                ]
            ]
        );

        static::assertArrayHasKey('post', $json);
    }

    /**
     * @return void
     */
    public function testPutIndex()
    {
        $post = static::dataPost();

        $json = static::httpRequestJson(
            'PUT',
            'posts/' . $post['post_id'],
            [
                'body' => [
                    'oauth_token' => self::$accessToken,
                    'post_body' => str_repeat(__METHOD__ . ' ', 10)
                ]
            ]
        );

        $jsonPostId = static::assertArrayHasKeyPath($json, 'post', 'post_id');
        static::assertEquals($post['post_id'], $jsonPostId);
    }
}

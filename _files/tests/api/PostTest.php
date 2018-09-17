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

    public function testGetIndex()
    {
        $thread = $this->dataThread();

        $jsonPosts = $this->httpRequestJson(
            'GET',
            'posts',
            [
                'query' => [
                    'thread_id' => $thread['thread_id'],
                    'oauth_token' => self::$accessToken
                ]
            ]
        );
        $this->assertArrayHasKey('posts', $jsonPosts);

        $post = $this->dataPost();
        $excludeFields = [
            '',
            'signature',
            'signature_html',
            'signature_plain_text'
        ];

        foreach ($excludeFields as $excludeField) {
            $jsonPost = $this->httpRequestJson(
                'GET',
                'posts/' . $post['post_id'],
                [
                    'query' => [
                        'oauth_token' => self::$accessToken,
                        'exclude_field' => $excludeField
                    ]
                ]
            );

            $this->assertArrayHasKey('post', $jsonPost);
            if ($excludeField) {
                $this->assertArrayNotHasKey($excludeField, $jsonPost);
            }
        }
    }

    public function testPostIndex()
    {
        $thread = $this->dataThread();

        $json = $this->httpRequestJson(
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

        $this->assertArrayHasKey('post', $json);
    }

    public function testPutIndex()
    {
        $thread = $this->dataThread();
        $post = $this->dataPost();

        // first post
        $jsonFirstPost = $this->httpRequestJson(
            'PUT',
            'threads/' . $thread['thread_id'],
            [
                'body' => [
                    'oauth_token' => self::$accessToken,
                    'post_body' => str_repeat(__METHOD__ . ' ', 10)
                ]
            ]
        );

        $this->assertArrayHasKey('post', $jsonFirstPost);

        $jsonPost = $this->httpRequestJson(
            'PUT',
            'posts/' . $post['post_id'],
            [
                'body' => [
                    'oauth_token' => self::$accessToken,
                    'post_body' => str_repeat(__METHOD__ . ' ', 10)
                ]
            ]
        );

        $this->assertArrayHasKey('post', $jsonPost);
    }
}
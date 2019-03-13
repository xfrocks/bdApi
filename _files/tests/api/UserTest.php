<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class UserTest extends ApiTestCase
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
        $user = static::dataUser();

        $jsonUsers = static::httpRequestJson('GET', 'users', [
            'query' => [
                'oauth_token' => self::$accessToken
            ]
        ]);
        static::assertArrayHasKey('users', $jsonUsers);

        // test exclude fields.
        $excludeFields = [
            '',
            'user_last_seen_date',
            'user_external_authentications',
            'user_dob_day',
            'user_dob_month',
            'user_dob_year',
            'user_has_password'
        ];

        foreach ($excludeFields as $excludeField) {
            $jsonUser = static::httpRequestJson(
                'GET',
                'users/' . $user['user_id'],
                [
                    'query' => [
                        'oauth_token' => self::$accessToken,
                        'exclude_field' => $excludeField ?: null
                    ]
                ]
            );

            static::assertArrayHasKey('user', $jsonUser);

            if ($excludeField !== '') {
                static::assertArrayNotHasKey($excludeField, $jsonUser);
            }
        }
    }

    /**
     * @return void
     */
    public function testPostIndex()
    {
        $now = time();

        $userEmail = 'tests_' . $now . '@local.com';
        $username = 'tests_' . $now;

        $json = static::httpRequestJson(
            'POST',
            'users',
            [
                'body' => [
                    'user_email' => $userEmail,
                    'username' => $username,
                    'password' => '123456',
                    'oauth_token' => self::$accessToken
                ]
            ]
        );

        static::assertArrayHasKey('user', $json);
        static::assertArrayHasKey('token', $json);

        $token = $json['token'];
        static::assertArrayHasKey('access_token', $token);
        static::assertArrayHasKey('expires_in', $token);
        static::assertArrayHasKey('scope', $token);
        static::assertArrayHasKey('refresh_token', $token);
    }

    /**
     * @return void
     */
    public function testPutIndex()
    {
        $user = static::dataUser();

        $json = static::httpRequestJson(
            'PUT',
            'users/' . $user['user_id'],
            [
                'body' => [
                    'oauth_token' => self::$accessToken
                ]
            ]
        );

        static::assertArrayHasKey('status', $json);
        static::assertEquals('ok', $json['status']);
    }
}

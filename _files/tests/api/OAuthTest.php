<?php

namespace tests\api;

use Base32\Base32;
use Otp\Otp;
use tests\bases\ApiTestCase;

class OAuthTest extends ApiTestCase
{
    /**
     * @return void
     */
    public function testGrantTypePassword()
    {
        $client = static::dataApiClient();
        $user = static::dataUser();
        $json = static::postPassword($client, $user);

        static::assertArrayHasKey('access_token', $json);
        static::assertArrayHasKey('expires_in', $json);
        static::assertArrayHasKey('scope', $json);

        static::assertEquals($user['user_id'], $json['user_id']);
    }

    /**
     * @return void
     */
    public function testGrantTypeRefreshToken()
    {
        $client = static::dataApiClient();
        $user = static::dataUser();
        $passwordJson = static::postPassword($client, $user);
        static::assertArrayHasKey('refresh_token', $passwordJson);

        $json = static::postOauthToken([
            'grant_type' => 'refresh_token',
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'refresh_token' => $passwordJson['refresh_token']
        ]);

        static::assertArrayHasKey('access_token', $json);
        static::assertArrayHasKey('refresh_token', $json);
    }

    /**
     * @return void
     */
    public function testGrantTypePasswordWithTfa()
    {
        $client = static::dataApiClient();
        $user = static::dataUser(4);

        $oAuthParams = [
            'grant_type' => 'password',
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'username' => $user['username'],
            'password' => $user['password']
        ];

        static::httpRequestJson('POST', 'oauth/token', [
            'form_params' => $oAuthParams
        ], false);

        $response = static::httpLatestResponse();
        static::assertEquals('202', strval($response->getStatusCode()), '202');

        static::assertNotEmpty($response->getHeader('X-Api-Tfa-Providers'));

        $oAuthParams['tfa_provider'] = 'totp';

        $otp = new Otp();
        $oAuthParams['code'] = $otp->totp(Base32::decode($user['tfa_secret']));

        $json = static::postOauthToken($oAuthParams);

        static::assertArrayHasKey('access_token', $json);
        static::assertArrayHasKey('expires_in', $json);
        static::assertArrayHasKey('scope', $json);

        static::assertEquals($user['user_id'], $json['user_id']);
    }
}

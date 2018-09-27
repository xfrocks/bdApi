<?php

namespace tests\api;

use Base32\Base32;
use Otp\Otp;
use tests\bases\ApiTestCase;

class OAuthTest extends ApiTestCase
{
    public function testGrantTypePassword()
    {
        $client = $this->dataApiClient();
        $user = $this->dataUser();
        $json = $this->postPassword($client, $user);

        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('expires_in', $json);
        $this->assertArrayHasKey('scope', $json);

        $this->assertEquals($user['user_id'], $json['user_id']);
    }

    public function testGrantTypeRefreshToken()
    {
        $client = $this->dataApiClient();
        $user = $this->dataUser();
        $passwordJson = $this->postPassword($client, $user);
        $this->assertArrayHasKey('refresh_token', $passwordJson);

        $json = $this->postOauthToken([
            'grant_type' => 'refresh_token',
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'refresh_token' => $passwordJson['refresh_token']
        ]);

        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('refresh_token', $json);
    }

    public function testGrantTypePasswordWithTfa()
    {
        $client = $this->dataApiClient();
        $user = $this->dataUser(4);

        $oAuthParams = [
            'grant_type' => 'password',
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'username' => $user['username'],
            'password' => $user['password']
        ];

        $this->httpRequestJson('POST', 'oauth/token', [
            'body' => $oAuthParams
        ], false);

        $response = $this->httpLatestResponse();
        $this->assertEquals('202', strval($response->getStatusCode()), '202');

        $this->assertNotEmpty($response->getHeader('X-Api-Tfa-Providers'));

        $oAuthParams['tfa_provider'] = 'totp';

        $otp = new Otp();
        $oAuthParams['code'] = $otp->totp(Base32::decode($user['tfa_secret']));

        $json = $this->postOauthToken($oAuthParams);

        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('expires_in', $json);
        $this->assertArrayHasKey('scope', $json);

        $this->assertEquals($user['user_id'], $json['user_id']);
    }
}

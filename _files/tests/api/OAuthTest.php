<?php

namespace tests\api;

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
    }

    public function testGrantTypeRefreshToken()
    {
        $client = $this->dataApiClient();
        $user = $this->dataUser(1);
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

    protected function postPassword(array $client, array $user)
    {
        return $this->postOauthToken([
            'grant_type' => 'password',
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'username' => $user['username'],
            'password' => $user['password']
        ]);
    }

    protected function postOauthToken(array $params)
    {
        return $this->httpRequestJson('POST', 'oauth/token', ['form_params' => $params]);
    }
}

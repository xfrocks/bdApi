<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class SubscriptionTest extends ApiTestCase
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

    public function testSubscribeSuccess()
    {
        $hubCallback = self::$apiRoot . 'index.php?tools/websub/echo-hub-challenge';
        $this->postSubscriptions($hubCallback);

        $this->assertEquals(202, static::httpLatestResponse()->getStatusCode());

        $json = $this->httpRequestJson('GET', 'notifications', [
            'query' => [
                'oauth_token' => self::$accessToken
            ]
        ]);

        $this->assertArrayHasKey('subscription_callback', $json);

        $response = $this->httpLatestResponse();
        $links = $response->getHeader('Link');

        $this->assertNotEmpty($links);
        $this->assertContains('rel=hub', $links);
        $this->assertContains('rel=self', $links);

        $this->postSubscriptions($hubCallback, 'unsubscribe');
        $this->assertEquals(202, $this->httpLatestResponse()->getStatusCode());

        $notifyJson = $this->httpRequestJson('GET', 'notifications', [
            'query' => [
                'oauth_token' => self::$accessToken
            ]
        ]);

        $this->assertArrayNotHasKey('subscription_callback', $notifyJson);
    }

    public function testSubscribeFailure()
    {
        $hubCallback = self::$apiRoot . 'index.php?tools/websub/echo-none';
        $this->postSubscriptions($hubCallback);

        $this->assertEquals(400, static::httpLatestResponse()->getStatusCode());
    }

    private function postSubscriptions($hubCallback, $hubMode = 'subscribe')
    {
        return static::httpRequest(
            'POST',
            'subscriptions',
            [
                'body' => [
                    'hub.callback' => $hubCallback,
                    'hub.mode' => $hubMode,
                    'hub.topic' => 'user_notification_me',
                    'oauth_token' => self::$accessToken,
                ],
                'exceptions' => false,
            ]
        );
    }
}

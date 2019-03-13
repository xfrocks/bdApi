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

    /**
     * @return void
     */
    public function testSubscribeSuccess()
    {
        $hubCallback = self::$apiRoot . 'index.php?tools/websub/echo-hub-challenge';
        $this->postSubscriptions($hubCallback);

        static::assertEquals(202, static::httpLatestResponse()->getStatusCode());

        $json = static::httpRequestJson('GET', 'notifications', [
            'query' => [
                'oauth_token' => self::$accessToken
            ]
        ]);

        static::assertArrayHasKey('subscription_callback', $json);

        $response = static::httpLatestResponse();
        $links = $response->getHeader('Link');

        static::assertNotEmpty($links);
        static::assertContains('rel=hub', $links);
        static::assertContains('rel=self', $links);

        $this->postSubscriptions($hubCallback, 'unsubscribe');
        static::assertEquals(202, static::httpLatestResponse()->getStatusCode());

        $notifyJson = static::httpRequestJson('GET', 'notifications', [
            'query' => [
                'oauth_token' => self::$accessToken
            ]
        ]);

        static::assertArrayNotHasKey('subscription_callback', $notifyJson);
    }

    /**
     * @return void
     */
    public function testSubscribeFailure()
    {
        $hubCallback = self::$apiRoot . 'index.php?tools/websub/echo-none';
        $this->postSubscriptions($hubCallback);

        static::assertEquals(400, static::httpLatestResponse()->getStatusCode());
    }

    /**
     * @param string $hubCallback
     * @param string $hubMode
     * @return \GuzzleHttp\Message\ResponseInterface
     */
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

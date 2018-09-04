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
    }

    public function testSubscribeFailure()
    {
        $hubCallback = self::$apiRoot . 'index.php?tools/websub/echo-none';
        $this->postSubscriptions($hubCallback);

        $this->assertEquals(400, static::httpLatestResponse()->getStatusCode());
    }

    private function postSubscriptions($hubCallback)
    {
        return static::httpRequest(
            'POST',
            'subscriptions',
            [
                'body' => [
                    'hub.callback' => $hubCallback,
                    'hub.mode' => 'subscribe',
                    'hub.topic' => 'user_notification_me',
                    'oauth_token' => self::$accessToken,
                ],
                'exceptions' => false,
            ]
        );
    }
}

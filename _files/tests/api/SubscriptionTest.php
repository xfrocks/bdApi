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

        $notificationsPath = 'notifications?oauth_token=' . self::$accessToken;
        $notificationsJson1 = $this->httpRequestJson('GET', $notificationsPath);
        $this->assertArrayHasKey('subscription_callback', $notificationsJson1);

        $response = $this->httpLatestResponse();
        $links = $response->getHeader('Link');

        $this->assertTrue(is_array($links));

        $hrefs = [];
        foreach ($links as $link) {
            if (!preg_match('#^<(.+)>; rel=(\w+)$#', $link, $matches)) {
                continue;
            }
            $hrefs[$matches[2]] = $matches[1];
        }
        $this->assertArrayHasKey('hub', $hrefs);
        $this->assertArrayHasKey('self', $hrefs);

        $this->postSubscriptions($hubCallback, 'unsubscribe');
        $this->assertEquals(202, $this->httpLatestResponse()->getStatusCode());

        $notificationJson2 = $this->httpRequestJson('GET', $notificationsPath);
        $this->assertArrayNotHasKey('subscription_callback', $notificationJson2);
    }

    public function testSubscribeFailure()
    {
        $hubCallback = self::$apiRoot . 'index.php?tools/websub/echo-none';
        $this->postSubscriptions($hubCallback);

        $this->assertEquals(400, static::httpLatestResponse()->getStatusCode());
    }

    private function postSubscriptions($hubCallback, $hubMode = 'subscribe')
    {
        $hubCallbackEncoded = rawurlencode($hubCallback);
        return static::httpRequest(
            'POST',
            "subscriptions?hub.callback={$hubCallbackEncoded}&hub.mode={$hubMode}&hub.topic=user_notification_me&oauth_token=" . self::$accessToken,
            [
                'exceptions' => false,
            ]
        );
    }
}

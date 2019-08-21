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
        static::$accessToken = $token['access_token'];
    }

    /**
     * @return void
     */
    public function testSubscribeSuccess()
    {
        $hubCallback = static::$apiRoot . 'index.php?tools/websub/echo-hub-challenge';
        $this->postSubscriptions($hubCallback);

        static::assertEquals(202, static::httpLatestResponse()->getStatusCode());

        $notificationsPath = 'notifications?oauth_token=' . static::$accessToken;
        $notificationsJson1 = static::httpRequestJson('GET', $notificationsPath);
        static::assertArrayHasKey('subscription_callback', $notificationsJson1);

        $response = static::httpLatestResponse();
        $links = $response->getHeader('Link');

        $hrefs = [];
        foreach ($links as $link) {
            if (preg_match('#^<(.+)>; rel=(\w+)$#', $link, $matches) === false) {
                continue;
            }
            $hrefs[$matches[2]] = $matches[1];
        }
        static::assertArrayHasKey('hub', $hrefs);
        static::assertArrayHasKey('self', $hrefs);

        $this->postSubscriptions($hubCallback, 'unsubscribe');
        static::assertEquals(202, static::httpLatestResponse()->getStatusCode());

        $notificationJson2 = static::httpRequestJson('GET', $notificationsPath);
        static::assertArrayNotHasKey('subscription_callback', $notificationJson2);
    }

    /**
     * @return void
     */
    public function testSubscribeFailure()
    {
        $hubCallback = static::$apiRoot . 'index.php?tools/websub/echo-none';
        $this->postSubscriptions($hubCallback);

        static::assertEquals(400, static::httpLatestResponse()->getStatusCode());
    }

    /**
     * @param string $hubCallback
     * @param string $hubMode
     * @return mixed|\Psr\Http\Message\ResponseInterface|null
     */
    private function postSubscriptions($hubCallback, $hubMode = 'subscribe')
    {
        $hubCallbackEncoded = rawurlencode($hubCallback);
        return static::httpRequest(
            'POST',
            "subscriptions?hub.callback={$hubCallbackEncoded}&hub.mode={$hubMode}&hub.topic=user_notification_me&oauth_token=" . static::$accessToken,
            [
                'exceptions' => false,
            ]
        );
    }
}

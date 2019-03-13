<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class IndexTest extends ApiTestCase
{
    /**
     * @return void
     */
    public function testReturns200()
    {
        $response = static::httpRequest('GET', 'index');
        static::assertEquals(200, $response->getStatusCode());
    }

    /**
     * @return void
     */
    public function testReturnsData()
    {
        $json = static::httpRequestJson('GET', 'index');
        static::assertArrayHasKey('links', $json, 'json has links');

        static::assertArrayHasKey('system_info', $json);
        $systemInfo = $json['system_info'];
        static::assertArrayHasKey('oauth/authorize', $systemInfo);
        static::assertArrayHasKey('oauth/token', $systemInfo);
    }
}

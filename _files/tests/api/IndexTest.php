<?php

namespace tests\api;

use tests\bases\ApiTestCase;

class IndexTest extends ApiTestCase
{
    public function testReturns200()
    {
        $response = $this->httpRequest('GET', 'index');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReturnsData()
    {
        $json = $this->httpRequestJson('GET', 'index');
        $this->assertArrayHasKey('links', $json, 'json has links');

        $this->assertArrayHasKey('system_info', $json);
        $systemInfo = $json['system_info'];
        $this->assertArrayHasKey('oauth/authorize', $systemInfo);
        $this->assertArrayHasKey('oauth/token', $systemInfo);
    }
}

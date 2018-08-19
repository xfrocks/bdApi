<?php

namespace tests\bases;

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    /**
     * @var \Psr\Http\Message\ResponseInterface|null
     */
    private $latestResponse = null;

    /**
     * @var \GuzzleHttp\Client
     */
    private static $http;

    /**
     * @var array|null
     */
    private static $testData = null;

    public static function setUpBeforeClass()
    {
        self::$http = new \GuzzleHttp\Client([
            'base_uri' => 'http://localhost/api/',
            'http_errors' => false,
        ]);
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    protected function httpLatestResponse()
    {
        return $this->latestResponse;
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param string $method
     * @param string $path
     * @param array $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function httpRequest($method, $path, array $options = [])
    {
        $uri = 'index.php?' . str_replace('?', '&', $path);
        $this->latestResponse = self::$http->request($method, $uri, $options);

        return $this->latestResponse;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $options
     * @return array
     */
    protected function httpRequestJson($method, $path, array $options = [])
    {
        $response = $this->httpRequest($method, $path, $options);

        $contentType = $response->getHeaders()['Content-Type'][0];
        $this->assertContains('application/json', $contentType);

        $json = json_decode($response->getBody(), true);
        $this->assertTrue(is_array($json));

        return $json;
    }

    /**
     * @param string ...$keys
     * @return mixed
     */
    protected function data(...$keys)
    {
        $message = 'Execute cli command `xfrocks-api:pre-test` to prepare test data';

        if (!is_array(self::$testData)) {
            self::$testData = [];

            $path = '/tmp/api-test.json';
            $this->assertFileExists($path, $message);

            $json = json_decode(file_get_contents($path) ?: '', true);
            $this->assertTrue(is_array($json), $message);

            self::$testData = $json;
        }

        $data = self::$testData;
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $data, $message);
            $data = $data[$key];
        }

        return $data;
    }

    protected function dataApiClient()
    {
        return $this->data('apiClient');
    }

    protected function dataUser($i = 0)
    {
        return $this->data('users', $i);
    }
}

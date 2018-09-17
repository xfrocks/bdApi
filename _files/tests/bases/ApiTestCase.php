<?php

namespace tests\bases;

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    public static $apiRoot = 'http://localhost/api/';

    /**
     * @var \GuzzleHttp\Message\ResponseInterface|null
     */
    private static $latestResponse = null;

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
            'base_url' => self::$apiRoot,
        ]);
    }

    /**
     * @return \GuzzleHttp\Message\ResponseInterface
     */
    protected static function httpLatestResponse()
    {
        /** @var \GuzzleHttp\Message\ResponseInterface $response */
        $response = self::$latestResponse;
        static::assertNotNull($response);

        return $response;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $options
     * @return \GuzzleHttp\Message\ResponseInterface
     */
    protected static function httpRequest($method, $path, array $options = [])
    {
        $uri = 'index.php?' . str_replace('?', '&', $path);
        $request = self::$http->createRequest($method, $uri, $options);
        self::$latestResponse = self::$http->send($request);

        return self::$latestResponse;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $options
     * @return array
     */
    protected static function httpRequestJson($method, $path, array $options = [])
    {
        $response = static::httpRequest($method, $path, $options);

        $contentType = $response->getHeaders()['Content-Type'][0];
        static::assertContains('application/json', $contentType);

        $json = json_decode(strval($response->getBody()), true);
        static::assertTrue(is_array($json));

        return $json;
    }

    /**
     * @param string|int ...$keys
     * @return mixed
     */
    protected static function data(...$keys)
    {
        if (!is_array(self::$testData)) {
            self::$testData = [];

            $path = '/tmp/api_test.json';
            static::assertFileExists($path);

            $json = json_decode(file_get_contents($path) ?: '', true);
            static::assertTrue(is_array($json));

            self::$testData = $json;
        }

        $data = self::$testData;
        foreach ($keys as $key) {
            static::assertArrayHasKey($key, $data);
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * @return array
     */
    protected static function dataApiClient()
    {
        return static::data('apiClient');
    }

    /**
     * @return array
     */
    protected static function dataForum()
    {
        return static::data('forum');
    }

    /**
     * @param int $i
     * @return array
     */
    protected static function dataUser($i = 0)
    {
        return static::data('users', $i);
    }

    /**
     * @param int $i
     * @return array
     */
    protected static function dataThread($i = 0)
    {
        return static::data('threads', $i);
    }

    /**
     * @param array $client
     * @param array $user
     * @return array
     */
    protected static function postPassword(array $client, array $user)
    {
        return static::postOauthToken([
            'grant_type' => 'password',
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'username' => $user['username'],
            'password' => $user['password']
        ]);
    }

    /**
     * @param array $params
     * @return array
     */
    protected static function postOauthToken(array $params)
    {
        return static::httpRequestJson('POST', 'oauth/token', ['body' => $params]);
    }
}

<?php


namespace App\Sdk\Api;


use App\Sdk\Client;

abstract class Api
{
    /**
     * @var Client
     */
    public $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $url
     * @param array $queryParams
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function httpGet($url, $queryParams = [])
    {
        $resp = $this->client->getHttpClient()->request('GET', $url, [
            'auth' => [
                $this->client->appKey,
                $this->client->appSecret,
            ],
            'query' => $queryParams,
        ]);
        if ($resp->getStatusCode() != 200) {
            return false;
        }
        return json_decode($resp->getBody()->getContents(), true);
    }

    /**
     * @param string $url
     * @param array $queryParams
     * @param array $data
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function httpPost($url, $queryParams = [], $data = [])
    {
        $resp = $this->client->getHttpClient()->request('POST', $url, [
            'auth' => [
                $this->client->appKey,
                $this->client->appSecret,
            ],
            'query' => $queryParams,
            'json' => $data,
        ]);
        if ($resp->getStatusCode() != 200) {
            return false;
        }
        return json_decode($resp->getBody()->getContents(), true);
    }
}

<?php


namespace App\Sdk;


class Client
{
    public $appKey;
    public $appSecret;
    public $apiUrl;
    /**
     * @var \GuzzleHttp\Client
     */
    private $_httpClient;

    public function __construct($appKey = null, $appSecret = null, $apiUrl = null)
    {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->apiUrl = $apiUrl;
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient()
    {
        if ($this->_httpClient == null) {
            $this->_httpClient = new \GuzzleHttp\Client([
                'base_uri' => $this->apiUrl,
            ]);
        }
        return $this->_httpClient;
    }
}

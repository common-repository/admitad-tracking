<?php

declare(strict_types=1);

namespace Admitad;

class AdmitadApi
{
    protected string $host = 'https://api.admitad.com';

    protected ?string $accessToken = null;

    protected ?string $refreshToken = null;

    public function __construct(protected AdmitadContainer $container) {}

    /**
     * example: get('/advertiser_info/', array('id' => 1)).
     */
    public function get(string $method, array $params = []): array
    {
        $content = $this->send(
            $this->host . $method,
            $params,
            'GET',
            $this->getRequestHeaders()
        );

        return json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * example: post('/advertiser_info/', array('id' => 1)).
     */
    public function post(string $method, array $params = []): mixed
    {
        $content = $this->send(
            $this->host . $method,
            $params,
            'POST',
            $this->getRequestHeaders()
        );

        return json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    public function isAuthorized(): ?string
    {
        return $this->accessToken;
    }

    public function authorize($clientId = null, $clientSecret = null): self
    {
        if ($this->accessToken) {
            return $this;
        }

        $settings = $this->container->getSettings();
        $clientId = $clientId ?: $settings->get('auth', 'client_id');
        $clientSecret = $clientSecret ?: $settings->get('auth', 'client_secret');

        if ($clientId && $clientSecret) {
            $this->selfAuthorize($clientId, $clientSecret, 'advertiser_info');
        }

        return $this;
    }

    public function selfAuthorize($clientId, $clientSecret, $scope): mixed
    {
        $response = $this->authorizeClient($clientId, $clientSecret, $scope);

        if (!isset($response['access_token'])) {
            return $response;
        }

        $accessToken = $response['access_token'];
        $refreshToken = $response['refresh_token'];

        $this
            ->setAccessToken($accessToken)
            ->setRefreshToken($refreshToken)
        ;

        return $response;
    }

    public function authorizeClient(string $clientId, string $clientSecret, string $scope): mixed
    {
        $query = [
            'client_id' => $clientId,
            'scope' => $scope,
            'grant_type' => 'client_credentials',
        ];

        $headers = [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
        ];

        $result = $this->send($this->host . '/token/', $query, 'POST', $headers);

        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    public function requestAccessToken(string $clientId, string $clientSecret, string $code, string $redirectUri): bool|string
    {
        $query = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ];

        return $this->send('/token/', $query, 'POST');
    }

    public function send(
        string $url,
        array $params = [],
        string $method = 'GET',
        array $headers = []
    ): bool|string {
        if (function_exists('curl_init')) {
            return $this->sendCurl($url, $params, $method, $headers);
        }

        return $this->sendFileGetContents($url, $params, $method, $headers);
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken($accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken($refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    protected function getRequestHeaders(): array
    {
        if (!$this->accessToken) {
            return [];
        }

        return ['Authorization: Bearer ' . $this->accessToken];
    }

    protected function sendCurl(
        string $url,
        array $params = [],
        string $method = 'GET',
        array $headers = []
    ): bool|string {
        $cl = curl_init($url);

        curl_setopt($cl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($cl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($cl, CURLOPT_HTTPHEADER, $headers);

        if ('POST' == $method) {
            curl_setopt($cl, CURLOPT_POST, 1);
            curl_setopt($cl, CURLOPT_POSTFIELDS, $params);
        }

        return curl_exec($cl);
    }

    protected function sendFileGetContents(
        string $url,
        array $params = [],
        string $method = 'GET',
        array $headers = []
    ): false|string {
        $context = stream_context_create(
            [
                'http' => [
                    'method' => $method,
                    'header' => implode(PHP_EOL, $headers),
                    'content' => http_build_query($params),
                ],
            ]
        );

        return file_get_contents($url, false, $context);
    }
}

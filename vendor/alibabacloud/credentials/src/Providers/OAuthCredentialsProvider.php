<?php

namespace AlibabaCloud\Credentials\Providers;

use AlibabaCloud\Credentials\Request\Request;
use AlibabaCloud\Credentials\Credential\RefreshResult;
use InvalidArgumentException;
use RuntimeException;

/**
 * @internal This class is intended for internal use within the package.
 * Class OAuthCredentialsProvider
 *
 * @package AlibabaCloud\Credentials\Providers
 */
class OAuthCredentialsProvider extends SessionCredentialsProvider
{
    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $signInUrl;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var int
     */
    private $accessTokenExpire;

    /**
     * @var int
     */
    private $connectTimeout = 5;

    /**
     * @var int
     */
    private $readTimeout = 10;

    /**
     * @var callable|null
     */
    private $tokenUpdateCallback;

    /**
     * OAuthCredentialsProvider constructor.
     *
     * @param array $params
     * @param array $options
     */
    public function __construct(array $params = [], array $options = [])
    {
        $this->filterOptions($options);
        $this->filterParams($params);
    }

    private function filterOptions(array $options)
    {
        if (isset($options['connectTimeout'])) {
            $this->connectTimeout = $options['connectTimeout'];
        }

        if (isset($options['readTimeout'])) {
            $this->readTimeout = $options['readTimeout'];
        }
    }

    private function filterParams(array $params)
    {
        $this->clientId = isset($params['clientId']) ? $params['clientId'] : null;
        $this->signInUrl = isset($params['signInUrl']) ? $params['signInUrl'] : null;
        $this->refreshToken = isset($params['refreshToken']) ? $params['refreshToken'] : null;
        $this->accessToken = isset($params['accessToken']) ? $params['accessToken'] : null;
        $this->accessTokenExpire = isset($params['accessTokenExpire']) ? (int) $params['accessTokenExpire'] : 0;
        $this->tokenUpdateCallback = isset($params['tokenUpdateCallback']) ? $params['tokenUpdateCallback'] : null;

        if (empty($this->clientId)) {
            throw new InvalidArgumentException('The clientId is empty.');
        }

        if (empty($this->signInUrl)) {
            throw new InvalidArgumentException('The url for sign-in is empty.');
        }
    }

    private function tryRefreshOAuthToken()
    {
        $parsedUrl = parse_url($this->signInUrl);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

        $requestUrl = $scheme . '://' . $host . '/v1/token';

        $formData = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $options = [
            'http_errors' => false,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $formData,
        ];

        $result = Request::createClient()->request('POST', $requestUrl, $options);

        if ($result->getStatusCode() !== 200) {
            throw new RuntimeException('Failed to refresh OAuth token, status code: ' . $result->getStatusCode());
        }

        $json = json_decode((string) $result->getBody(), true);
        if (empty($json) || empty($json['access_token']) || empty($json['refresh_token'])) {
            throw new RuntimeException('Failed to refresh OAuth token: ' . (string) $result->getBody());
        }

        $this->accessToken = $json['access_token'];
        $this->refreshToken = $json['refresh_token'];
        $this->accessTokenExpire = time() + (isset($json['expires_in']) ? (int) $json['expires_in'] : 3600);
    }

    /**
     * @return RefreshResult
     * @throws RuntimeException
     */
    public function refreshCredentials()
    {
        $now = time();
        if (!empty($this->refreshToken)
            && (empty($this->accessToken) || $this->accessTokenExpire === 0
                || $this->accessTokenExpire - $now <= 1200)) {
            $this->tryRefreshOAuthToken();
        }

        $parsedUrl = parse_url($this->signInUrl);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

        $requestUrl = $scheme . '://' . $host . '/v1/exchange';

        $options = [
            'http_errors' => false,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
        ];

        $result = Request::createClient()->request('POST', $requestUrl, $options);

        if ($result->getStatusCode() !== 200) {
            throw new RuntimeException('Get session token from OAuth failed, statusCode: '
                . $result->getStatusCode() . ', result: ' . (string) $result->getBody());
        }

        $json = json_decode((string) $result->getBody(), true);
        if (empty($json) || empty($json['accessKeyId']) || empty($json['accessKeySecret'])
            || empty($json['securityToken'])) {
            throw new RuntimeException('Refresh session token from OAuth failed, fail to get credentials: '
                . (string) $result->getBody());
        }

        $expiration = isset($json['expiration']) ? \strtotime($json['expiration']) : 0;

        if ($this->tokenUpdateCallback && is_callable($this->tokenUpdateCallback)) {
            try {
                call_user_func(
                    $this->tokenUpdateCallback,
                    $this->refreshToken,
                    $this->accessToken,
                    $json['accessKeyId'],
                    $json['accessKeySecret'],
                    $json['securityToken'],
                    $this->accessTokenExpire,
                    $expiration
                );
            } catch (\Exception $e) {
                // Warning only
            }
        }

        return new RefreshResult(new Credentials([
            'accessKeyId' => $json['accessKeyId'],
            'accessKeySecret' => $json['accessKeySecret'],
            'securityToken' => $json['securityToken'],
            'expiration' => $expiration,
            'providerName' => $this->getProviderName(),
        ]), $this->getStaleTime($expiration));
    }

    public function key()
    {
        return 'oauth#' . $this->signInUrl . '#' . $this->clientId;
    }

    public function getProviderName()
    {
        return 'oauth';
    }
}

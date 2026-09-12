<?php

namespace AlibabaCloud\Credentials\Providers;

use AlibabaCloud\Credentials\Request\Request;
use AlibabaCloud\Credentials\Credential\RefreshResult;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use RuntimeException;

/**
 * @internal This class is intended for internal use within the package.
 * Class CloudSSOCredentialsProvider
 *
 * @package AlibabaCloud\Credentials\Providers
 */
class CloudSSOCredentialsProvider extends SessionCredentialsProvider
{
    /**
     * @var string
     */
    private $signInUrl;

    /**
     * @var string
     */
    private $accountId;

    /**
     * @var string
     */
    private $accessConfig;

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
     * CloudSSOCredentialsProvider constructor.
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
        $this->signInUrl = isset($params['signInUrl']) ? $params['signInUrl'] : null;
        $this->accountId = isset($params['accountId']) ? $params['accountId'] : null;
        $this->accessConfig = isset($params['accessConfig']) ? $params['accessConfig'] : null;
        $this->accessToken = isset($params['accessToken']) ? $params['accessToken'] : null;
        $this->accessTokenExpire = isset($params['accessTokenExpire']) ? (int) $params['accessTokenExpire'] : 0;

        if (empty($this->accessToken) || $this->accessTokenExpire === 0
            || $this->accessTokenExpire - time() <= 0) {
            throw new InvalidArgumentException('CloudSSO access token is empty or expired, please re-login with cli.');
        }

        if (empty($this->signInUrl) || empty($this->accountId) || empty($this->accessConfig)) {
            throw new InvalidArgumentException('CloudSSO sign in url, account id, and access config cannot be empty.');
        }
    }

    /**
     * @return RefreshResult
     * @throws RuntimeException
     */
    public function refreshCredentials()
    {
        $parsedUrl = parse_url($this->signInUrl);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

        $requestUrl = $scheme . '://' . $host . '/cloud-credentials';

        $body = json_encode([
            'AccountId' => $this->accountId,
            'AccessConfigurationId' => $this->accessConfig,
        ]);

        $options = [
            'http_errors' => false,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'body' => $body,
        ];

        $result = Request::createClient()->request('POST', $requestUrl, $options);

        if ($result->getStatusCode() !== 200) {
            throw new RuntimeException('Get session token from CloudSSO failed, statusCode: '
                . $result->getStatusCode() . ', result: ' . (string) $result->getBody());
        }

        $json = json_decode((string) $result->getBody(), true);
        if (!isset($json['CloudCredential'])) {
            throw new RuntimeException('Get session token from CloudSSO failed, fail to get credentials.');
        }

        $credentials = $json['CloudCredential'];
        if (!isset($credentials['AccessKeyId']) || !isset($credentials['AccessKeySecret'])
            || !isset($credentials['SecurityToken'])) {
            throw new RuntimeException('Get session token from CloudSSO failed, fail to get credentials.');
        }

        $expiration = \strtotime($credentials['Expiration']);

        return new RefreshResult(new Credentials([
            'accessKeyId' => $credentials['AccessKeyId'],
            'accessKeySecret' => $credentials['AccessKeySecret'],
            'securityToken' => $credentials['SecurityToken'],
            'expiration' => $expiration,
            'providerName' => $this->getProviderName(),
        ]), $this->getStaleTime($expiration));
    }

    public function key()
    {
        return 'cloud_sso#' . $this->signInUrl . '#' . $this->accountId . '#' . $this->accessConfig;
    }

    public function getProviderName()
    {
        return 'cloud_sso';
    }
}

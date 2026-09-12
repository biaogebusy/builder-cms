<?php

namespace AlibabaCloud\Credentials\Providers;

use AlibabaCloud\Credentials\Utils\Helper;
use RuntimeException;

/**
 * @internal This class is intended for internal use within the package. 
 * Class CLIProfileCredentialsProvider
 *
 * @package AlibabaCloud\Credentials\Providers
 */
class CLIProfileCredentialsProvider implements CredentialsProvider
{
    private static $oauthBaseUrlMap = [
        'CN' => 'https://oauth.aliyun.com',
        'INTL' => 'https://oauth.alibabacloud.com',
    ];

    private static $oauthClientMap = [
        'CN' => '4038181954557748008',
        'INTL' => '4103531455503354461',
    ];

    /**
     * @var string
     */
    private $profileName;

    /**
     * @var CredentialsProvider
     */
    private $credentialsProvider;


    /**
     * CLIProfileCredentialsProvider constructor.
     *
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        $this->filterProfileName($params);
    }

    private function filterProfileName(array $params)
    {
        if (Helper::envNotEmpty('ALIBABA_CLOUD_PROFILE')) {
            $this->profileName = Helper::env('ALIBABA_CLOUD_PROFILE');
        }

        if (isset($params['profileName'])) {
            $this->profileName = $params['profileName'];
        }
    }

    /**
     * @return bool
     */
    private function shouldReloadCredentialsProvider()
    {
        if (is_null($this->credentialsProvider)) {
            return true;
        }

        return false;
    }

    /**
     * @return CredentialsProvider
     */
    protected function reloadCredentialsProvider($profileFile, $profileName)
    {
        if (!Helper::inOpenBasedir($profileFile)) {
            throw new RuntimeException('Unable to open credentials file: ' . $profileFile);
        }

        if (!\is_readable($profileFile) || !\is_file($profileFile)) {
            throw new RuntimeException('Credentials file is not readable: ' . $profileFile);
        }

        $jsonContent = \file_get_contents($profileFile);
        $fileArray = json_decode($jsonContent, true);

        if (\is_array($fileArray) && !empty($fileArray)) {
            if (is_null($profileName) || $profileName === '') {
                $profileName = $fileArray['current'];
            }
            if (isset($fileArray['profiles'])) {
                foreach ($fileArray['profiles'] as $profile) {
                    if (Helper::unsetReturnNull($profile, 'name') === $profileName) {
                        switch (Helper::unsetReturnNull($profile, 'mode')) {
                            case 'AK':
                                return new StaticAKCredentialsProvider([
                                    'accessKeyId' => Helper::unsetReturnNull($profile, 'access_key_id'),
                                    'accessKeySecret' => Helper::unsetReturnNull($profile, 'access_key_secret'),
                                ]);
                            case 'StsToken':
                                return new StaticSTSCredentialsProvider([
                                    'accessKeyId' => Helper::unsetReturnNull($profile, 'access_key_id'),
                                    'accessKeySecret' => Helper::unsetReturnNull($profile, 'access_key_secret'),
                                    'securityToken' => Helper::unsetReturnNull($profile, 'sts_token'),
                                ]);
                            case 'RamRoleArn':
                                $innerProvider = new StaticAKCredentialsProvider([
                                    'accessKeyId' => Helper::unsetReturnNull($profile, 'access_key_id'),
                                    'accessKeySecret' => Helper::unsetReturnNull($profile, 'access_key_secret'),
                                ]);
                                return new RamRoleArnCredentialsProvider([
                                    'credentialsProvider' => $innerProvider,
                                    'roleArn' => Helper::unsetReturnNull($profile, 'ram_role_arn'),
                                    'roleSessionName' => Helper::unsetReturnNull($profile, 'ram_session_name'),
                                    'durationSeconds' => Helper::unsetReturnNull($profile, 'expired_seconds'),
                                    'policy' => Helper::unsetReturnNull($profile, 'policy'),
                                    'externalId' => Helper::unsetReturnNull($profile, 'external_id'),
                                    'stsRegionId' => Helper::unsetReturnNull($profile, 'sts_region'),
                                    'enableVpc' => Helper::unsetReturnNull($profile, 'enable_vpc'),
                                ]);
                            case 'EcsRamRole':
                                return new EcsRamRoleCredentialsProvider([
                                    'roleName' => Helper::unsetReturnNull($profile, 'ram_role_name'),
                                ]);
                            case 'OIDC':
                                return new OIDCRoleArnCredentialsProvider([
                                    'roleArn' => Helper::unsetReturnNull($profile, 'ram_role_arn'),
                                    'oidcProviderArn' => Helper::unsetReturnNull($profile, 'oidc_provider_arn'),
                                    'oidcTokenFilePath' => Helper::unsetReturnNull($profile, 'oidc_token_file'),
                                    'roleSessionName' => Helper::unsetReturnNull($profile, 'ram_session_name'),
                                    'durationSeconds' => Helper::unsetReturnNull($profile, 'expired_seconds'),
                                    'policy' => Helper::unsetReturnNull($profile, 'policy'),
                                    'stsRegionId' => Helper::unsetReturnNull($profile, 'sts_region'),
                                    'enableVpc' => Helper::unsetReturnNull($profile, 'enable_vpc'),
                                ]);
                            case 'ChainableRamRoleArn':
                                $previousProvider = $this->reloadCredentialsProvider($profileFile, Helper::unsetReturnNull($profile, 'source_profile'));
                                return new RamRoleArnCredentialsProvider([
                                    'credentialsProvider' => $previousProvider,
                                    'roleArn' => Helper::unsetReturnNull($profile, 'ram_role_arn'),
                                    'roleSessionName' => Helper::unsetReturnNull($profile, 'ram_session_name'),
                                    'durationSeconds' => Helper::unsetReturnNull($profile, 'expired_seconds'),
                                    'policy' => Helper::unsetReturnNull($profile, 'policy'),
                                    'externalId' => Helper::unsetReturnNull($profile, 'external_id'),
                                    'stsRegionId' => Helper::unsetReturnNull($profile, 'sts_region'),
                                    'enableVpc' => Helper::unsetReturnNull($profile, 'enable_vpc'),
                                ]);
                            case 'CloudSSO':
                                return new CloudSSOCredentialsProvider([
                                    'signInUrl' => Helper::unsetReturnNull($profile, 'cloud_sso_sign_in_url'),
                                    'accountId' => Helper::unsetReturnNull($profile, 'cloud_sso_account_id'),
                                    'accessConfig' => Helper::unsetReturnNull($profile, 'cloud_sso_access_config'),
                                    'accessToken' => Helper::unsetReturnNull($profile, 'access_token'),
                                    'accessTokenExpire' => Helper::unsetReturnNull($profile, 'cloud_sso_access_token_expire'),
                                ]);
                            case 'OAuth':
                                $siteType = strtoupper(Helper::unsetReturnNull($profile, 'oauth_site_type') ?: '');
                                if (!isset(self::$oauthBaseUrlMap[$siteType])) {
                                    throw new RuntimeException('Invalid OAuth site type, support CN or INTL.');
                                }
                                $oauthSignInUrl = self::$oauthBaseUrlMap[$siteType];
                                $oauthClientId = self::$oauthClientMap[$siteType];
                                return new OAuthCredentialsProvider([
                                    'signInUrl' => $oauthSignInUrl,
                                    'clientId' => $oauthClientId,
                                    'refreshToken' => Helper::unsetReturnNull($profile, 'oauth_refresh_token'),
                                    'accessToken' => Helper::unsetReturnNull($profile, 'oauth_access_token'),
                                    'accessTokenExpire' => Helper::unsetReturnNull($profile, 'oauth_access_token_expire'),
                                    'tokenUpdateCallback' => $this->createOAuthTokenUpdateCallback($profileFile, $profileName),
                                ]);
                            case 'External':
                                return new ExternalCredentialsProvider([
                                    'processCommand' => (string) Helper::unsetReturnNull($profile, 'process_command'),
                                    'credentialUpdateCallback' => $this->createExternalCredentialUpdateCallback($profileFile, $profileName),
                                ]);
                            default:
                                throw new RuntimeException('Unsupported credential mode from CLI credentials file: ' . (string) Helper::unsetReturnNull($profile, 'mode'));
                        }
                    }
                }
            }
        }
        throw new RuntimeException('Failed to get credential from CLI credentials file: ' . $profileFile);
    }
    /**
     * Get credential.
     *
     * @return Credentials
     * @throws RuntimeException
     */
    public function getCredentials()
    {
        if (Helper::envNotEmpty('ALIBABA_CLOUD_CLI_PROFILE_DISABLED') && Helper::env('ALIBABA_CLOUD_CLI_PROFILE_DISABLED') === true) {
            throw new RuntimeException('CLI credentials file is disabled');
        }
        $cliProfileFile = self::getDefaultFile();
        if ($this->shouldReloadCredentialsProvider()) {
            $this->credentialsProvider = $this->reloadCredentialsProvider($cliProfileFile, $this->profileName);
        }

        $credentials = $this->credentialsProvider->getCredentials();
        return new Credentials([
            'accessKeyId' => $credentials->getAccessKeyId(),
            'accessKeySecret' => $credentials->getAccessKeySecret(),
            'securityToken' => $credentials->getSecurityToken(),
            'providerName' => $this->getProviderName() . '/' . $this->credentialsProvider->getProviderName(),
        ]);
    }

    /**
     * @param string $profileFile
     * @param string $profileName
     * @return callable
     */
    private function createOAuthTokenUpdateCallback($profileFile, $profileName)
    {
        return function ($refreshToken, $accessToken, $accessKeyId, $accessKeySecret, $securityToken, $accessTokenExpire, $stsExpire) use ($profileFile, $profileName) {
            try {
                $this->updateOAuthTokens($profileFile, $profileName, $refreshToken, $accessToken, $accessKeyId, $accessKeySecret, $securityToken, $accessTokenExpire, $stsExpire);
            } catch (\Exception $e) {
                // Warning only
            }
        };
    }

    /**
     * @param string $profileFile
     * @param string $profileName
     * @return callable
     */
    private function createExternalCredentialUpdateCallback($profileFile, $profileName)
    {
        return function ($accessKeyId, $accessKeySecret, $securityToken, $expiration) use ($profileFile, $profileName) {
            try {
                $this->updateExternalCredentials($profileFile, $profileName, $accessKeyId, $accessKeySecret, $securityToken, $expiration);
            } catch (\Exception $e) {
                // Warning only
            }
        };
    }

    /**
     * @param string $profileFile
     * @param string $profileName
     * @param string $refreshToken
     * @param string $accessToken
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @param string $securityToken
     * @param int $accessTokenExpire
     * @param int $stsExpire
     */
    private function updateOAuthTokens($profileFile, $profileName, $refreshToken, $accessToken, $accessKeyId, $accessKeySecret, $securityToken, $accessTokenExpire, $stsExpire)
    {
        if (!file_exists($profileFile)) {
            return;
        }

        $jsonContent = file_get_contents($profileFile);
        $config = json_decode($jsonContent, true);
        if (empty($config) || !isset($config['profiles'])) {
            return;
        }

        $oauthProfile = $this->findOAuthProfile($config, $profileName);
        if ($oauthProfile === null) {
            return;
        }

        $oauthProfile['oauth_refresh_token'] = $refreshToken;
        $oauthProfile['oauth_access_token'] = $accessToken;
        $oauthProfile['oauth_access_token_expire'] = $accessTokenExpire;
        $oauthProfile['access_key_id'] = $accessKeyId;
        $oauthProfile['access_key_secret'] = $accessKeySecret;
        $oauthProfile['sts_token'] = $securityToken;
        $oauthProfile['sts_expiration'] = $stsExpire;

        foreach ($config['profiles'] as &$p) {
            if (isset($p['name']) && $p['name'] === $oauthProfile['name']) {
                $p = $oauthProfile;
                break;
            }
        }
        unset($p);

        file_put_contents($profileFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param string $profileFile
     * @param string $profileName
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @param string $securityToken
     * @param int $expiration
     */
    private function updateExternalCredentials($profileFile, $profileName, $accessKeyId, $accessKeySecret, $securityToken, $expiration)
    {
        if (!file_exists($profileFile)) {
            return;
        }

        $jsonContent = file_get_contents($profileFile);
        $config = json_decode($jsonContent, true);
        if (empty($config) || !isset($config['profiles'])) {
            return;
        }

        $externalProfile = $this->findExternalProfile($config, $profileName);
        if ($externalProfile === null) {
            return;
        }

        $externalProfile['access_key_id'] = $accessKeyId;
        $externalProfile['access_key_secret'] = $accessKeySecret;
        $externalProfile['sts_token'] = $securityToken;
        $externalProfile['sts_expiration'] = $expiration;

        foreach ($config['profiles'] as &$p) {
            if (isset($p['name']) && $p['name'] === $externalProfile['name']) {
                $p = $externalProfile;
                break;
            }
        }
        unset($p);

        file_put_contents($profileFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array $config
     * @param string $profileName
     * @return array|null
     */
    private function findOAuthProfile($config, $profileName)
    {
        if (!isset($config['profiles'])) {
            return null;
        }
        foreach ($config['profiles'] as $p) {
            if (isset($p['name']) && $p['name'] === $profileName) {
                if (isset($p['mode']) && $p['mode'] === 'OAuth') {
                    return $p;
                }
                if (!empty($p['source_profile'])) {
                    return $this->findOAuthProfile($config, $p['source_profile']);
                }
                return null;
            }
        }
        return null;
    }

    /**
     * @param array $config
     * @param string $profileName
     * @return array|null
     */
    private function findExternalProfile($config, $profileName)
    {
        if (!isset($config['profiles'])) {
            return null;
        }
        foreach ($config['profiles'] as $p) {
            if (isset($p['name']) && $p['name'] === $profileName) {
                if (isset($p['mode']) && $p['mode'] === 'External') {
                    return $p;
                }
                if (!empty($p['source_profile'])) {
                    return $this->findExternalProfile($config, $p['source_profile']);
                }
                return null;
            }
        }
        return null;
    }

    /**
     * Get the default credential file.
     *
     * @return string
     */
    private function getDefaultFile()
    {
        return Helper::getHomeDirectory() .
            DIRECTORY_SEPARATOR .
            '.aliyun' .
            DIRECTORY_SEPARATOR .
            'config.json';
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'cli_profile';
    }
}

<?php

namespace AlibabaCloud\Credentials\Providers;

use AlibabaCloud\Credentials\Utils\Helper;
use InvalidArgumentException;
use RuntimeException;

/**
 * @internal This class is intended for internal use within the package.
 * Class ExternalCredentialsProvider
 *
 * @package AlibabaCloud\Credentials\Providers
 */
class ExternalCredentialsProvider implements CredentialsProvider
{
    /**
     * @var string
     */
    private $processCommand;

    /**
     * @var int
     */
    private $timeout = 60;

    /**
     * @var callable|null
     */
    private $credentialUpdateCallback;

    /**
     * @var Credentials|null
     */
    private $credentials;

    /**
     * @var int
     */
    private $expirationTimestamp = 0;

    /**
     * ExternalCredentialsProvider constructor.
     *
     * @param array $params
     * @param array $options
     */
    public function __construct(array $params = [], array $options = [])
    {
        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
        }

        $this->processCommand = isset($params['processCommand']) ? $params['processCommand'] : null;
        $this->credentialUpdateCallback = isset($params['credentialUpdateCallback'])
            ? $params['credentialUpdateCallback'] : null;

        if (empty($this->processCommand)) {
            throw new InvalidArgumentException('process_command is empty');
        }
    }

    /**
     * @return Credentials
     */
    public function getCredentials()
    {
        if ($this->needUpdateCredential()) {
            $credential = $this->getCredentialsInternal();
            $this->credentials = $credential;
            $this->expirationTimestamp = $credential->getExpiration() > 0 ? $credential->getExpiration() : 0;
            $this->invokeCredentialUpdateCallback($credential);
        }

        return new Credentials([
            'accessKeyId' => $this->credentials->getAccessKeyId(),
            'accessKeySecret' => $this->credentials->getAccessKeySecret(),
            'securityToken' => $this->credentials->getSecurityToken(),
            'expiration' => $this->credentials->getExpiration(),
            'providerName' => $this->getProviderName(),
        ]);
    }

    /**
     * @return Credentials
     */
    public function getCredentialsInternal()
    {
        $stdout = $this->executeCommand();
        $json = json_decode($stdout, true);
        if (!is_array($json)) {
            throw new RuntimeException('failed to parse external command output: ' . json_last_error_msg());
        }

        if (empty($json['access_key_id']) || empty($json['access_key_secret'])) {
            throw new RuntimeException('invalid credential response: access_key_id or access_key_secret is empty');
        }

        if (isset($json['mode']) && $json['mode'] === 'StsToken' && empty($json['sts_token'])) {
            throw new RuntimeException('invalid StsToken credential response: sts_token is empty');
        }

        $expiration = !empty($json['expiration']) ? strtotime($json['expiration']) : 0;
        if ($expiration === false) {
            $expiration = 0;
        }

        return new Credentials([
            'accessKeyId' => $json['access_key_id'],
            'accessKeySecret' => $json['access_key_secret'],
            'securityToken' => isset($json['sts_token']) ? $json['sts_token'] : null,
            'expiration' => $expiration,
            'providerName' => $this->getProviderName(),
        ]);
    }

    /**
     * @return string
     */
    private function executeCommand()
    {
        $args = Helper::splitProcessCommand($this->processCommand);
        $command = implode(' ', array_map('escapeshellarg', $args));
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('failed to execute external command');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $startedAt = time();
        while (true) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (time() - $startedAt >= $this->timeout) {
                proc_terminate($process);
                proc_close($process);
                throw new RuntimeException('command process timed out after ' . ($this->timeout * 1000) . ' milliseconds');
            }
            usleep(10000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException('failed to execute external command: exit status ' . $exitCode . "\nstderr: " . $stderr);
        }

        return $stdout;
    }

    /**
     * @return bool
     */
    private function needUpdateCredential()
    {
        if ($this->credentials === null) {
            return true;
        }
        if ($this->expirationTimestamp === 0) {
            return true;
        }
        return $this->expirationTimestamp - time() <= 180;
    }

    private function invokeCredentialUpdateCallback(Credentials $credential)
    {
        if (!$this->credentialUpdateCallback || !is_callable($this->credentialUpdateCallback)) {
            return;
        }
        try {
            call_user_func(
                $this->credentialUpdateCallback,
                $credential->getAccessKeyId(),
                $credential->getAccessKeySecret(),
                $credential->getSecurityToken(),
                $this->expirationTimestamp
            );
        } catch (\Exception $e) {
            // Warning only
        }
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'external';
    }
}

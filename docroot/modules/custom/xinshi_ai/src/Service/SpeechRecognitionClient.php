<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Transcribes microphone WAV files using the site's encrypted DeepSeek credential. */
final class SpeechRecognitionClient {

  public const MAX_BYTES = 8 * 1024 * 1024;

  public function __construct(
    private readonly ClientInterface $http,
    private readonly WebSearchCredentials $credentials,
    private readonly AccountProxyInterface $account,
    private readonly LockBackendInterface $lock,
    private readonly UuidInterface $uuid,
    private readonly LoggerInterface $logger,
    private readonly ?LocalUsageProducer $usage = NULL,
  ) {}

  /** Availability is checked for the current principal without returning any secret. */
  public function available(): bool {
    $this->authorize();
    return $this->credentials->has();
  }

  /** Validate a complete recording before issuing one paid request; never retry it here. */
  public function transcribe(string $audio): array {
    $this->authorize();
    self::validateAudio($audio);
    try {
      $key = $this->credentials->get();
    }
    catch (\Throwable) {
      throw new HttpException(503, 'speech_not_configured');
    }
    $lockName = 'xinshi_ai.speech.' . $this->account->id();
    if (!$this->lock->acquire($lockName, 150.0)) {
      throw new HttpException(429, 'speech_busy');
    }
    $attempt = NULL;
    $dispatch = 'not_sent';
    try {
      $attempt = $this->prepareUsage();
      $dispatch = 'unknown';
      $response = $this->http->request('POST', 'https://api.deepseek.com/v1/tools/asr', [
        'headers' => ['Authorization' => 'Bearer ' . $key],
        'multipart' => [['name' => 'file', 'contents' => $audio, 'filename' => 'speech.wav',
          'headers' => ['Content-Type' => 'audio/wav']]],
        'timeout' => 120, 'connect_timeout' => 5, 'allow_redirects' => FALSE,
        'http_errors' => FALSE, 'stream' => TRUE,
      ]);
      $dispatch = 'sent';
      $body = $response->getBody();
      try {
        $status = $response->getStatusCode();
        if ($status !== 200) {
          throw new HttpException(in_array($status, [429, 503, 504], TRUE) ? $status : 502,
            'speech_provider_failed');
        }
        $json = Utils::copyToString($body, 131073);
        if (strlen($json) > 131072) throw new \RuntimeException();
        $result = json_decode($json, TRUE, 16, JSON_THROW_ON_ERROR);
        if (!is_array($result) || !is_string($result['text'] ?? NULL) ||
          !mb_check_encoding($result['text'], 'UTF-8') || mb_strlen($result['text']) > 16000 ||
          !is_int($result['duration_ms'] ?? NULL) || $result['duration_ms'] < 0 ||
          $result['duration_ms'] > 120000 || !is_string($result['request_id'] ?? NULL) ||
          !preg_match('/^[\x21-\x7e]{1,191}$/D', $result['request_id'])) {
          throw new \RuntimeException();
        }
      }
      finally {
        $body->close();
      }
      $raw = ['duration_ms' => $result['duration_ms'],
        'billed_seconds' => max(1, intdiv($result['duration_ms'] + 999, 1000))];
      $this->observeUsage($attempt, [
        'outcome' => 'succeeded', 'dispatch_state' => 'sent',
        'provider_request_id' => $result['request_id'],
        'usage' => ['normalizer_version' => 'speech-asr-v1', 'quality' => 'reported',
          'raw_usage' => $raw, 'raw_usage_hash' => hash('sha256', json_encode($raw))],
      ]);
      return ['request_id' => $result['request_id'], 'text' => $result['text'],
        'duration_ms' => $result['duration_ms']];
    }
    catch (\Throwable $error) {
      $this->observeUsage($attempt, [
        'outcome' => $dispatch === 'unknown' ? 'unknown' : ($dispatch === 'not_sent' ? 'not_sent' : 'failed'),
        'dispatch_state' => $dispatch, 'error_code' => 'speech_provider_failed', 'usage' => NULL,
      ]);
      throw $error instanceof HttpException ? $error : new HttpException(502, 'speech_provider_failed');
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  private function authorize(): void {
    if (!$this->account->isAuthenticated()) throw new HttpException(403, 'speech_access_denied');
  }

  /** The browser supplies canonical mono PCM; arbitrary uploaded media is not part of this route. */
  private static function validateAudio(string $audio): void {
    $size = strlen($audio);
    if ($size > self::MAX_BYTES) throw new HttpException(413, 'speech_too_large');
    if ($size < 46 || substr($audio, 0, 4) !== 'RIFF' || substr($audio, 8, 8) !== 'WAVEfmt ' ||
      substr($audio, 36, 4) !== 'data') throw new HttpException(400, 'speech_invalid_audio');
    $format = unpack('Vsize/vformat/vchannels/Vrate/Vbytes/vblock/vbits', substr($audio, 16, 20));
    if ($format !== ['size' => 16, 'format' => 1, 'channels' => 1, 'rate' => 16000,
      'bytes' => 32000, 'block' => 2, 'bits' => 16] || ($size - 44) % 2 !== 0 ||
      unpack('V', substr($audio, 4, 4))[1] !== $size - 8 ||
      unpack('V', substr($audio, 40, 4))[1] !== $size - 44 || $size - 44 > 120 * 32000) {
      throw new HttpException(400, 'speech_invalid_audio');
    }
  }

  private function prepareUsage(): ?string {
    if ($this->usage === NULL) return NULL;
    try {
      $prepared = $this->usage->prepare([
        'operation_id' => $this->uuid->generate(), 'logical_call_id' => 'speech',
        'feature' => 'speech_recognition', 'stage' => 'asr', 'payer' => 'platform',
        'billing_role' => 'auxiliary', 'provider_account_ref' => 'deepseek-tools',
        'requested_model' => 'deepseek-asr', 'actor_user_id' => (string) $this->account->id(),
        'task_id' => NULL,
      ]);
      return $prepared['attempt_id'];
    }
    catch (\Throwable) {
      $this->logger->error('Speech usage intent could not be recorded.');
      if ($this->usage->mode() === LocalUsageProducer::MODE_ENFORCE) {
        throw new HttpException(503, 'speech_usage_unavailable');
      }
      return NULL;
    }
  }

  private function observeUsage(?string $attempt, array $observation): void {
    if ($attempt === NULL) return;
    try {
      $this->usage?->observe($attempt, $observation);
    }
    catch (\Throwable) {
      // Keep the prepared intent for reconciliation; do not encourage a second paid call.
      $this->logger->error('Speech usage observation could not be recorded for @attempt.', [
        '@attempt' => $attempt,
      ]);
    }
  }

}

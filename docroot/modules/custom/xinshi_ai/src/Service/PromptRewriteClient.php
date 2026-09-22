<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Orders the text-to-image prompt pre-rewrite from the chat service (UB2.6).
 *
 * The rewrite used to be a browser call to Node made before the job existed,
 * metered there as an operation of its own. The worker now orders it under the
 * job's UUID: the auxiliary attempt joins the root operation, the client cannot
 * run the step at all, and Node lets each operation order it once. The request
 * carries no user session; it is signed with the shared secret of the producer
 * the chat service delivers its usage events with, and Node verifies it the way
 * this site verifies those events (ProducerIdentity).
 *
 * The rewrite is best effort: whatever fails, the original prompt is generated.
 *
 * Not final so the image pipeline can be tested with a double (like ImageUsageRecorder).
 */
class PromptRewriteClient {

  public const STEP = 'query-transformer';
  public const PATH = '/chat/sub-steps';
  /** The step runs live-data tools; a generation is never held longer than this. */
  public const TIMEOUT_SECONDS = 60;
  public const CONNECT_TIMEOUT_SECONDS = 5;
  private const CANONICAL_VERSION = 'sub-step-v1';
  private const LANGUAGE_PATTERN = '/^[A-Za-z]{2,8}(-[A-Za-z0-9]{1,8})*\z/';
  /**
   * Only prompts that name live data (weather, dates, relative times) gain from
   * the rewrite. The list errs on the wide side: a false positive costs one
   * small auxiliary call, a false negative loses the live data.
   */
  private const DYNAMIC_DATA_HINT = '/今天|明天|昨天|现在|此刻|当前|实时|最新|天气|气温|温度|几号|日期|星期|today|tonight|tomorrow|yesterday|right now|current|weather|temperature/iu';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
    private readonly ProducerIdentity $producers,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Whether a prompt is worth one rewrite call.
   */
  public static function needsRewrite(string $prompt): bool {
    return preg_match(self::DYNAMIC_DATA_HINT, $prompt) === 1;
  }

  /**
   * An origin the service listens on: http(s), host, optional port, nothing else.
   */
  public static function isServiceUrl(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts) && in_array($parts['scheme'] ?? '', ['http', 'https'], TRUE)
      && ($parts['host'] ?? '') !== '' && ($parts['path'] ?? '') === '' && !isset($parts['query'])
      && !isset($parts['fragment']) && !isset($parts['user']) && !isset($parts['pass']);
  }

  /**
   * The bytes both sides sign: the semantic fields joined by NUL, so PHP and
   * Node never have to agree on JSON escaping. Version it if it changes.
   */
  public static function canonicalBody(array $request): string {
    return implode("\0", [self::CANONICAL_VERSION, $request['operation_id'], $request['actor_user_id'],
      $request['step'], $request['task_id'] ?? '', $request['language'] ?? '', $request['prompt']]);
  }

  /**
   * Whether a chat service address is configured; without one the step is skipped.
   */
  public function isConfigured(): bool {
    return $this->serviceUrl() !== NULL;
  }

  /**
   * Orders the rewrite of a job's prompt under the job's operation.
   *
   * @return string|null
   *   The rewritten prompt, or NULL when there is none: the step is not
   *   configured, the producer is not registered, the service refused or
   *   failed, or the model returned the prompt unchanged.
   */
  public function rewrite(NodeInterface $job, string $prompt): ?string {
    $serviceUrl = $this->serviceUrl();
    if ($serviceUrl === NULL) {
      return NULL;
    }
    $uuid = $job->uuid();
    $producerId = $this->producerId();
    $secret = $this->producers->secretOf($producerId);
    if ($secret === NULL) {
      $this->logger->warning('image_job @job: prompt rewrite skipped, producer @producer has no registered secret', [
        '@job' => $uuid, '@producer' => $producerId,
      ]);
      return NULL;
    }
    $taskId = $job->get('field_task_id')->value;
    $language = $job->language()->getId();
    $request = [
      'operation_id' => $uuid,
      'actor_user_id' => (string) $job->getOwnerId(),
      'step' => self::STEP,
      'task_id' => is_string($taskId) && $taskId !== '' ? $taskId : NULL,
      'language' => preg_match(self::LANGUAGE_PATTERN, $language) ? $language : NULL,
      'prompt' => $prompt,
    ];
    $url = $serviceUrl . self::PATH;
    $timestamp = (string) $this->time->getRequestTime();
    $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    $signature = ProducerIdentity::sign($secret, $producerId, $timestamp, $nonce, 'POST',
      (string) parse_url($url, PHP_URL_PATH), self::canonicalBody($request));

    try {
      $response = $this->httpClient->request('POST', $url, [
        'json' => $request,
        'headers' => [
          ProducerIdentity::HEADER_PRODUCER => $producerId,
          ProducerIdentity::HEADER_TIMESTAMP => $timestamp,
          ProducerIdentity::HEADER_NONCE => $nonce,
          ProducerIdentity::HEADER_SIGNATURE => $signature,
        ],
        'timeout' => self::TIMEOUT_SECONDS,
        'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
        'http_errors' => FALSE,
      ]);
    }
    catch (GuzzleException $e) {
      // The message may echo the request; only the class says what went wrong.
      $this->logger->warning('image_job @job: prompt rewrite unreachable (@error); the original prompt is used', [
        '@job' => $uuid, '@error' => (new \ReflectionClass($e))->getShortName(),
      ]);
      return NULL;
    }
    $status = $response->getStatusCode();
    $body = json_decode((string) $response->getBody(), TRUE);
    if ($status !== 200) {
      $code = is_array($body) && is_string($body['code'] ?? NULL) ? $body['code'] : 'http_' . $status;
      // A repeat order is refused by design (once per operation); every other
      // refusal is worth an operator's look.
      $this->logger->log($code === 'sub_step_budget_exceeded' ? 'notice' : 'warning',
        'image_job @job: prompt rewrite refused (@code); the original prompt is used', [
          '@job' => $uuid, '@code' => $code,
        ]);
      return NULL;
    }
    $revised = is_array($body) && is_string($body['content'] ?? NULL) ? trim($body['content']) : '';
    return $revised !== '' && $revised !== $prompt ? $revised : NULL;
  }

  private function serviceUrl(): ?string {
    $url = $this->configFactory->get('xinshi_ai.settings')->get('harness.service.url');
    $url = is_string($url) ? rtrim(trim($url), '/') : '';
    return $url !== '' && self::isServiceUrl($url) ? $url : NULL;
  }

  private function producerId(): string {
    $id = $this->configFactory->get('xinshi_ai.settings')->get('harness.service.producer_id');
    return is_string($id) && $id !== '' ? $id : 'chat-node';
  }

}

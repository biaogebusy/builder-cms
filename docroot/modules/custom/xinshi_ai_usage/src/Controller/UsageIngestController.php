<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use Drupal\xinshi_ai_usage\Service\ServiceAuthException;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * POST /internal/ai-metering/v1/events — usage event ingest for registered producers.
 *
 * Batch errors use `{ code, message, request_id, retryable }`; per-event
 * results use `{ results: [{ event_id, status, code? }] }`. Only `accepted`
 * and `duplicate` confirm a durable fact.
 */
final class UsageIngestController extends ControllerBase {

  public const MAX_BODY_BYTES = 1048576;

  public function __construct(
    private readonly ProducerIdentity $identity,
    private readonly UsageIngestService $ingest,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai_usage.producer_identity'),
      $container->get('xinshi_ai_usage.ingest'),
    );
  }

  public function ingest(Request $request): JsonResponse {
    $requestId = (string) ($request->headers->get('X-Request-ID') ?: bin2hex(random_bytes(8)));
    $body = (string) $request->getContent();
    if (strlen($body) > self::MAX_BODY_BYTES) {
      return $this->failure(413, 'batch_too_large', 'request body exceeds 1 MiB', $requestId, FALSE);
    }
    try {
      $producer = $this->identity->authenticate($request);
    }
    catch (ServiceAuthException $e) {
      return $this->failure(401, $e->authCode, $e->getMessage(), $requestId, FALSE);
    }
    $decoded = json_decode($body, TRUE, 32);
    $events = is_array($decoded) ? ($decoded['events'] ?? NULL) : NULL;
    if (!is_array($events) || !array_is_list($events)) {
      return $this->failure(400, 'invalid_request', 'body must be { "events": [...] }', $requestId, FALSE);
    }
    if (count($events) > UsageIngestService::MAX_EVENTS) {
      return $this->failure(400, 'too_many_events', 'at most ' . UsageIngestService::MAX_EVENTS . ' events per batch', $requestId, FALSE);
    }
    $results = $this->ingest->ingest($events, $producer['producer_id'], $producer['site_id']);
    return new JsonResponse(['results' => $results, 'request_id' => $requestId], 200, [
      'Cache-Control' => 'private, no-store',
    ]);
  }

  private function failure(int $status, string $code, string $message, string $requestId, bool $retryable): JsonResponse {
    return new JsonResponse([
      'code' => $code, 'message' => $message, 'request_id' => $requestId, 'retryable' => $retryable,
    ], $status, ['Cache-Control' => 'private, no-store']);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\RegisteredSites;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Read-only usage reports of the authenticated user (`/api/v3/ai/usage/me/*`, UB3.1).
 *
 * The account comes from the session or OAuth token, the site from the
 * producer registration; neither can be chosen by a request parameter, and a
 * request that tries is refused rather than silently narrowed. Responses are
 * financial data of one person: never cached by any shared layer.
 */
final class UsageReportController extends ControllerBase {

  /** Query parameters that would widen the scope; their presence is a refusal. */
  private const SCOPE_PARAMETERS = ['uid', 'user', 'user_id', 'userId', 'actor', 'actor_user_id', 'account',
    'account_id', 'site', 'site_id'];

  public function __construct(
    private readonly UsageReportService $reports,
    private readonly AccountInterface $account,
    private readonly Settings $settings,
    private readonly ProducerVault $vault,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai_usage.report'),
      $container->get('current_user'),
      $container->get('settings'),
      $container->get('xinshi_ai_usage.producer_vault'),
      $container->get('datetime.time'),
    );
  }

  /**
   * GET /api/v3/ai/usage/me/summary.
   */
  public function summary(Request $request): JsonResponse {
    return $this->report($request, function (string $site, string $actor, array $query): array {
      return $this->reports->summary($site, $actor, $this->reports->parseFilter($query, $this->nowMs()));
    });
  }

  /**
   * GET /api/v3/ai/usage/me/timeseries.
   */
  public function timeseries(Request $request): JsonResponse {
    return $this->report($request, function (string $site, string $actor, array $query): array {
      $granularity = is_string($query['granularity'] ?? NULL) ? $query['granularity'] : 'day';
      $filter = $this->reports->parseFilter($query, $this->nowMs(), $granularity);
      return $this->reports->timeseries($site, $actor, $filter, $granularity);
    });
  }

  /**
   * GET /api/v3/ai/usage/me/operations.
   */
  public function operations(Request $request): JsonResponse {
    return $this->report($request, function (string $site, string $actor, array $query): array {
      $filter = $this->reports->parseFilter($query, $this->nowMs());
      $limit = is_numeric($query['limit'] ?? NULL) ? (int) $query['limit'] : UsageReportService::DEFAULT_LIMIT;
      $cursor = is_string($query['cursor'] ?? NULL) && $query['cursor'] !== '' ? $query['cursor'] : NULL;
      $status = is_string($query['status'] ?? NULL) && $query['status'] !== '' ? $query['status'] : NULL;
      return $this->reports->operations($site, $actor, $filter, $cursor, $limit, $status);
    });
  }

  /**
   * GET /api/v3/ai/usage/me/operations/{operation}.
   */
  public function operation(Request $request, string $operation): JsonResponse {
    return $this->report($request, function (string $site, string $actor, array $query) use ($operation): array {
      $filter = $this->reports->parseFilter(['timezone' => $query['timezone'] ?? NULL], $this->nowMs());
      $result = $this->reports->operation($site, $actor, $operation, $filter['timezone']);
      if ($result === NULL) {
        // Not found and not yours look the same: no enumeration of other users' operations.
        throw new UsageReportException('not_found', 'operation not found');
      }
      return $result;
    });
  }

  private function report(Request $request, callable $build): JsonResponse {
    $requestId = (string) ($request->headers->get('X-Request-ID') ?: bin2hex(random_bytes(8)));
    $query = $request->query->all();
    foreach (self::SCOPE_PARAMETERS as $parameter) {
      if (array_key_exists($parameter, $query)) {
        return $this->failure(403, 'forbidden', "the report is scoped to the authenticated user; $parameter is not accepted", $requestId);
      }
    }
    if ($this->account->isAnonymous()) {
      return $this->failure(403, 'forbidden', 'authentication required', $requestId);
    }
    $sites = RegisteredSites::list($this->settings, $this->vault);
    if ($sites === []) {
      return $this->failure(503, 'site_unresolved', 'no usage site is registered on this installation', $requestId);
    }
    try {
      $body = $build($sites[0], (string) $this->account->id(), $query);
    }
    catch (UsageReportException $e) {
      $status = match ($e->reportCode) {
        'not_found' => 404,
        'range_too_large' => 422,
        default => 400,
      };
      return $this->failure($status, $e->reportCode, $e->getMessage(), $requestId);
    }
    return $this->respond($body + ['request_id' => $requestId], 200);
  }

  private function failure(int $status, string $code, string $message, string $requestId): JsonResponse {
    return $this->respond(['code' => $code, 'message' => $message, 'request_id' => $requestId,
      'retryable' => $status === 503], $status);
  }

  private function respond(array $body, int $status): JsonResponse {
    $response = new JsonResponse($body, $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

  private function nowMs(): int {
    return (int) round($this->time->getCurrentMicroTime() * 1000);
  }

}

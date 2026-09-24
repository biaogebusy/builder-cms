<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\RegisteredSites;
use Drupal\xinshi_ai_usage\Service\SiteUsageReportService;
use Drupal\xinshi_ai_usage\Service\UsageQualityReportService;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Site-wide admin usage reports (`/api/v3/ai/admin/reports/*`, UB3.3).
 *
 * Quantity endpoints (summary/timeseries/breakdown) require
 * `view site ai usage`. The costs endpoint additionally requires
 * `view ai supplier costs`. The site is fixed to the registered site;
 * any `site` query parameter is refused, matching the user report pattern.
 *
 * Responses are admin-level financial data: never cached by any shared layer.
 */
final class SiteUsageReportController extends ControllerBase {

  /** Query parameters that would widen or change the scope; their presence is a refusal. */
  private const SCOPE_PARAMETERS = ['site', 'site_id'];

  public function __construct(
    private readonly SiteUsageReportService $reports,
    private readonly AccountInterface $account,
    private readonly Settings $settings,
    private readonly ProducerVault $vault,
    private readonly TimeInterface $time,
    private readonly UsageQualityReportService $qualityReports,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai_usage.site_report'),
      $container->get('current_user'),
      $container->get('settings'),
      $container->get('xinshi_ai_usage.producer_vault'),
      $container->get('datetime.time'),
      $container->get('xinshi_ai_usage.quality_report'),
    );
  }

  /**
   * GET /api/v3/ai/admin/reports/summary.
   */
  public function summary(Request $request): JsonResponse {
    return $this->report($request, function (string $site, array $query): array {
      return $this->reports->summary($site, $this->reports->parseFilter($query, $this->nowMs()));
    }, 'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/timeseries.
   */
  public function timeseries(Request $request): JsonResponse {
    return $this->report($request, function (string $site, array $query): array {
      $granularity = is_string($query['granularity'] ?? NULL) ? $query['granularity'] : 'day';
      $filter = $this->reports->parseFilter($query, $this->nowMs(), $granularity);
      return $this->reports->timeseries($site, $filter, $granularity);
    }, 'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/breakdown/{dimension}.
   *
   * Dimension must be one of SiteUsageReportService::DIMENSIONS.
   */
  public function breakdown(Request $request, string $dimension): JsonResponse {
    return $this->report($request, function (string $site, array $query) use ($dimension): array {
      $filter = $this->reports->parseFilter($query, $this->nowMs());
      $limit = is_numeric($query['limit'] ?? NULL) ? (int) $query['limit'] : 50;
      return $this->reports->breakdown($site, $filter, $dimension, $limit);
    }, 'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/costs/{dimension}.
   *
   * Purchase cost data. Requires both site-usage and supplier-costs permissions.
   */
  public function costs(Request $request, string $dimension): JsonResponse {
    return $this->report($request, function (string $site, array $query) use ($dimension): array {
      $filter = $this->reports->parseFilter($query, $this->nowMs());
      $limit = is_numeric($query['limit'] ?? NULL) ? (int) $query['limit'] : 50;
      return $this->reports->costs($site, $filter, $dimension, $limit);
    }, 'view site ai usage,view ai supplier costs');
  }

  /**
   * GET /api/v3/ai/admin/reports/consistency.
   *
   * Cost checks are included only for accounts that may see costs.
   */
  public function consistency(Request $request): JsonResponse {
    return $this->report($request, function (string $site, array $query): array {
      $filter = $this->reports->parseFilter($query, $this->nowMs());
      return $this->reports->consistency($site, $filter, $this->account->hasPermission('view ai supplier costs'));
    }, 'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/operations.
   *
   * The site's operations in the window (admin drilldown). Every row carries
   * the actor, which the user list never shows.
   */
  public function operations(Request $request): JsonResponse {
    return $this->report($request, function (string $site, array $query): array {
      $filter = $this->reports->parseFilter($query, $this->nowMs());
      $limit = is_numeric($query['limit'] ?? NULL) ? (int) $query['limit'] : UsageReportService::DEFAULT_LIMIT;
      $cursor = is_string($query['cursor'] ?? NULL) && $query['cursor'] !== '' ? $query['cursor'] : NULL;
      $status = is_string($query['status'] ?? NULL) && $query['status'] !== '' ? $query['status'] : NULL;
      return $this->reports->operations($site, $filter, $cursor, $limit, $status);
    }, 'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/operations/{operation}.
   *
   * One operation with its attempts and deliveries; the whole operation, not
   * just the window. Unlike the user endpoint, a missing operation is a plain
   * 404: there is no other user to protect from enumeration.
   */
  public function operation(Request $request, string $operation): JsonResponse {
    return $this->report($request, function (string $site, array $query) use ($operation): array {
      $filter = $this->reports->parseFilter(['timezone' => $query['timezone'] ?? NULL], $this->nowMs());
      $result = $this->reports->operation($site, $operation, $filter['timezone']);
      if ($result === NULL) {
        throw new UsageReportException('not_found', 'operation not found');
      }
      return $result;
    }, 'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/quality.
   *
   * Read-only quality report (UB3.5a). The unpriced family is included only
   * for accounts that may see supplier costs; everything else needs only
   * `view site ai usage`.
   */
  public function quality(Request $request): JsonResponse {
    return $this->report($request, fn(string $site, array $query): array =>
      $this->qualityReports->quality($site, $query,
        $this->account->hasPermission('view ai supplier costs'), $this->nowMs()),
      'view site ai usage');
  }

  /**
   * GET /api/v3/ai/admin/reports/quality/items.
   *
   * The deduplicated work queue of the quality report (UB3.5a); read-only.
   */
  public function qualityItems(Request $request): JsonResponse {
    return $this->report($request, fn(string $site, array $query): array =>
      $this->qualityReports->items($site, $query,
        $this->account->hasPermission('view ai supplier costs'), $this->nowMs()),
      'view site ai usage');
  }

  /**
   * Shared authorization, site resolution and response envelope for all endpoints.
   *
   * @param string $permissions
   *   Comma-separated permission list; all must pass (Drupal AND semantics).
   */
  private function report(Request $request, callable $build, string $permissions): JsonResponse {
    $requestId = (string) ($request->headers->get('X-Request-ID') ?: bin2hex(random_bytes(8)));
    $query = $request->query->all();
    foreach (self::SCOPE_PARAMETERS as $parameter) {
      if (array_key_exists($parameter, $query)) {
        return $this->failure(403, 'forbidden',
          "the report is scoped to the registered site; $parameter is not accepted", $requestId);
      }
    }
    if ($this->account->isAnonymous()) {
      return $this->failure(403, 'forbidden', 'authentication required', $requestId);
    }
    foreach (explode(',', $permissions) as $perm) {
      $perm = trim($perm);
      if ($perm === '') {
        continue;
      }
      if (!$this->account->hasPermission($perm)) {
        return $this->failure(403, 'forbidden', 'insufficient permissions', $requestId);
      }
    }
    $sites = RegisteredSites::list($this->settings, $this->vault);
    if ($sites === []) {
      return $this->failure(503, 'site_unresolved', 'no usage site is registered on this installation', $requestId);
    }
    try {
      $body = $build($sites[0], $query);
    }
    catch (UsageReportException $e) {
      $status = match ($e->reportCode) {
        'not_found' => 404,
        'forbidden' => 403,
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

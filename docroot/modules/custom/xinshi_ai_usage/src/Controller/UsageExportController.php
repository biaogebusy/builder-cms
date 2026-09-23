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
use Drupal\xinshi_ai_usage\Service\UsageExportService;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * CSV exports of the user and the site-wide report (UB3.4).
 *
 * The body of a POST is the same filter the report endpoints take, plus
 * `kind` and, for breakdown exports, `dimension`. A user export is scoped to
 * the token holder like the user report; a site export needs the explicit
 * export permission and, for cost columns, the supplier-cost permission.
 * Jobs are owned by their requester: another account gets 404, never the
 * file. Downloads are private, uncached and gone after the job expires.
 */
final class UsageExportController extends ControllerBase {

  private const SCOPE_PARAMETERS = ['site', 'site_id'];
  private const USER_SCOPE_PARAMETERS = ['uid', 'user', 'user_id', 'userId', 'actor', 'actor_user_id', 'account',
    'account_id'];

  public function __construct(
    private readonly UsageExportService $exports,
    private readonly UsageReportService $userReports,
    private readonly SiteUsageReportService $siteReports,
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
      $container->get('xinshi_ai_usage.export'),
      $container->get('xinshi_ai_usage.report'),
      $container->get('xinshi_ai_usage.site_report'),
      $container->get('current_user'),
      $container->get('settings'),
      $container->get('xinshi_ai_usage.producer_vault'),
      $container->get('datetime.time'),
    );
  }

  /** POST /api/v3/ai/usage/me/exports. */
  public function createUser(Request $request): JsonResponse {
    return $this->run($request, UsageExportService::SCOPE_USER, function (string $site, array $body, string $id): array {
      foreach (self::USER_SCOPE_PARAMETERS as $parameter) {
        if (array_key_exists($parameter, $body)) {
          throw new UsageReportException('forbidden', "the export is scoped to the authenticated user; $parameter is not accepted");
        }
      }
      $filter = $this->userReports->parseFilter($body, $this->nowMs());
      return $this->exports->create($site, UsageExportService::SCOPE_USER, $this->uid(),
        (string) ($body['kind'] ?? ''), $filter, [], $this->nowMs());
    });
  }

  /** POST /api/v3/ai/admin/reports/exports. */
  public function createSite(Request $request): JsonResponse {
    return $this->run($request, UsageExportService::SCOPE_SITE, function (string $site, array $body, string $id): array {
      $filter = $this->siteReports->parseFilter($body, $this->nowMs());
      $includeCosts = !empty($body['include_costs']);
      if ($includeCosts && !$this->account->hasPermission('view ai supplier costs')) {
        throw new UsageReportException('forbidden', 'cost columns need the view ai supplier costs permission');
      }
      $options = ['include_costs' => $includeCosts];
      if (isset($body['dimension'])) {
        $options['dimension'] = (string) $body['dimension'];
      }
      return $this->exports->create($site, UsageExportService::SCOPE_SITE, $this->uid(),
        (string) ($body['kind'] ?? ''), $filter, $options, $this->nowMs());
    }, Response::HTTP_ACCEPTED);
  }

  /** GET /api/v3/ai/usage/me/exports/{export}. */
  public function statusUser(Request $request, int $export): JsonResponse {
    return $this->status($request, UsageExportService::SCOPE_USER, $export);
  }

  /** GET /api/v3/ai/admin/reports/exports/{export}. */
  public function statusSite(Request $request, int $export): JsonResponse {
    return $this->status($request, UsageExportService::SCOPE_SITE, $export);
  }

  /** GET /api/v3/ai/usage/me/exports/{export}/download. */
  public function downloadUser(Request $request, int $export): Response {
    return $this->download($request, UsageExportService::SCOPE_USER, $export);
  }

  /** GET /api/v3/ai/admin/reports/exports/{export}/download. */
  public function downloadSite(Request $request, int $export): Response {
    return $this->download($request, UsageExportService::SCOPE_SITE, $export);
  }

  private function run(Request $request, string $scope, callable $build, int $status = Response::HTTP_ACCEPTED): JsonResponse {
    $requestId = $this->requestId($request);
    if ($this->account->isAnonymous()) {
      return $this->failure(403, 'forbidden', 'authentication required', $requestId);
    }
    $body = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($body)) {
      return $this->failure(400, 'invalid_request', 'the body must be a JSON object', $requestId);
    }
    foreach (self::SCOPE_PARAMETERS as $parameter) {
      if (array_key_exists($parameter, $body)) {
        return $this->failure(403, 'forbidden', "the export is scoped to the registered site; $parameter is not accepted", $requestId);
      }
    }
    $sites = RegisteredSites::list($this->settings, $this->vault);
    if ($sites === []) {
      return $this->failure(503, 'site_unresolved', 'no usage site is registered on this installation', $requestId);
    }
    try {
      $job = $build($sites[0], $body, $requestId);
    }
    catch (UsageReportException $e) {
      return $this->failure($this->statusOf($e), $e->reportCode, $e->getMessage(), $requestId);
    }
    return $this->respond($this->describe($job, $scope) + ['request_id' => $requestId], $status);
  }

  private function status(Request $request, string $scope, int $export): JsonResponse {
    $requestId = $this->requestId($request);
    if ($this->account->isAnonymous()) {
      return $this->failure(403, 'forbidden', 'authentication required', $requestId);
    }
    $job = $this->exports->load($export, $scope, $this->uid(), $this->nowMs());
    if ($job === NULL) {
      return $this->failure(404, 'not_found', 'export not found', $requestId);
    }
    return $this->respond($this->describe($job, $scope) + ['request_id' => $requestId], 200);
  }

  private function download(Request $request, string $scope, int $export): Response {
    $requestId = $this->requestId($request);
    if ($this->account->isAnonymous()) {
      return $this->failure(403, 'forbidden', 'authentication required', $requestId);
    }
    $job = $this->exports->load($export, $scope, $this->uid(), $this->nowMs());
    if ($job === NULL) {
      return $this->failure(404, 'not_found', 'export not found', $requestId);
    }
    $path = $this->exports->filePath($job);
    if ($path === NULL) {
      return $this->failure(410, 'expired', 'the export has expired; create it again', $requestId);
    }
    $response = new BinaryFileResponse($path, 200, [
      'Content-Type' => 'text/csv; charset=utf-8',
      'Cache-Control' => 'private, no-store',
      'X-Content-Type-Options' => 'nosniff',
    ], FALSE);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, UsageExportService::downloadName($job));
    return $response;
  }

  /** The job for the client: the file name stays internal, the download URL is what it needs. */
  private function describe(array $job, string $scope): array {
    unset($job['file_name'], $job['site_id']);
    $base = $scope === UsageExportService::SCOPE_USER ? '/api/v3/ai/usage/me/exports' : '/api/v3/ai/admin/reports/exports';
    return $job + [
      'status_url' => "$base/{$job['id']}",
      'download_url' => $job['status'] === 'ready' ? "$base/{$job['id']}/download" : NULL,
    ];
  }

  private function statusOf(UsageReportException $e): int {
    return match ($e->reportCode) {
      'not_found' => 404,
      'forbidden' => 403,
      'range_too_large' => 422,
      'export_unavailable' => 503,
      default => 400,
    };
  }

  private function uid(): string {
    return (string) $this->account->id();
  }

  private function requestId(Request $request): string {
    return (string) ($request->headers->get('X-Request-ID') ?: bin2hex(random_bytes(8)));
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

<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Drush\Commands;

use Drupal\xinshi_ai_usage\Service\AttemptReconcileService;
use Drupal\xinshi_ai_usage\Service\LocalUsageException;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lists and reconciles attempts whose provider outcome is unknown (UB2.5).
 */
final class UsageReconcileCommands extends DrushCommands {

  use AutowireTrait;

  public const ATTEMPTS = 'xinshi-ai-usage:attempts';
  public const RECONCILE = 'xinshi-ai-usage:reconcile';

  public function __construct(
    #[Autowire(service: 'xinshi_ai_usage.reconcile')]
    private readonly AttemptReconcileService $reconcile,
  ) {
    parent::__construct();
  }

  /**
   * Lists projected attempts by state; unknown attempts wait for a verdict.
   */
  #[CLI\Command(name: self::ATTEMPTS)]
  #[CLI\Option(name: 'site', description: 'Only attempts of this site id; default is every site.')]
  #[CLI\Option(name: 'state', description: 'Projected state to list: unknown (default), failed, succeeded, not_sent, prepared.')]
  #[CLI\Option(name: 'producer', description: 'Only attempts of this producer, e.g. cms-image.')]
  #[CLI\Option(name: 'limit', description: 'Maximum rows, at most 200.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:attempts', description: 'Unknown attempts of every producer, newest first.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:attempts --producer=cms-image --site=example.com', description: 'Unknown image attempts of one site.')]
  public function attempts(array $options = ['site' => NULL, 'state' => 'unknown', 'producer' => NULL, 'limit' => 50]): int {
    $rows = $this->reconcile->attempts(self::option($options, 'site'), (string) $options['state'],
      (int) $options['limit'], self::option($options, 'producer'));
    if ($rows === []) {
      $this->logger()->notice(sprintf('No attempts in state "%s". Run xinshi-ai-usage:process first if recent events are not projected yet.', $options['state']));
      return self::EXIT_SUCCESS;
    }
    $this->io()->table(
      ['attempt', 'operation', 'producer', 'feature', 'call#', 'state', 'dispatch', 'error', 'model', 'gateway request', 'images', 'started (UTC)'],
      array_map(static fn(array $row): array => [
        $row['attempt_id'],
        $row['operation_id'],
        $row['producer_id'],
        $row['feature'],
        $row['logical_call_id'] . '#' . $row['attempt_no'],
        $row['state'],
        $row['dispatch_state'],
        (string) $row['error_code'],
        (string) ($row['resolved_model'] ?? $row['requested_model']),
        (string) $row['gateway_request_id'],
        $row['images_generated'] === NULL ? '' : (string) $row['images_generated'],
        gmdate('Y-m-d H:i:s', intdiv((int) $row['started_at'], 1000)),
      ], $rows),
    );
    $this->io()->text(sprintf('Only attempts of producer "%s" can be reconciled here; Node attempts use /chat/metering/attempts on the Node side.',
      LocalUsageProducer::PRODUCER_ID));
    return self::EXIT_SUCCESS;
  }

  /**
   * Records the operator's verdict on an unknown image attempt as a new observation.
   */
  #[CLI\Command(name: self::RECONCILE)]
  #[CLI\Argument(name: 'attemptId', description: 'The attempt id from xinshi-ai-usage:attempts.')]
  #[CLI\Option(name: 'outcome', description: 'Required: succeeded, failed or not_sent.')]
  #[CLI\Option(name: 'images', description: 'Images the supplier billed for a succeeded call, from the gateway or supplier console.')]
  #[CLI\Option(name: 'usage-json', description: 'Raw supplier response or usage object as JSON; normalized like a live response (succeeded only).')]
  #[CLI\Option(name: 'resolved-model', description: 'Model the supplier actually served, when known.')]
  #[CLI\Option(name: 'gateway-request-id', description: 'Gateway request id, when the event has none.')]
  #[CLI\Option(name: 'provider-request-id', description: 'Supplier request id, when known.')]
  #[CLI\Option(name: 'note', description: 'Where the verdict comes from (ticket, console export); logged, not stored in the event.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:reconcile att_… --outcome=succeeded --images=4 --note="gateway log 2026-09-21"', description: 'The supplier generated and billed four images.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:reconcile att_… --outcome=not_sent', description: 'The gateway has no record of the request.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:reconcile att_… --outcome=failed', description: 'The supplier rejected the request; nothing was billed.')]
  public function reconcile(string $attemptId, array $options = ['outcome' => NULL, 'images' => NULL,
    'usage-json' => NULL, 'resolved-model' => NULL, 'gateway-request-id' => NULL, 'provider-request-id' => NULL,
    'note' => NULL]): int {
    $verdict = ['outcome' => (string) ($options['outcome'] ?? '')];
    if (self::option($options, 'images') !== NULL) {
      if (!preg_match('/^\d+$/', (string) $options['images'])) {
        $this->logger()->error('--images must be a non-negative integer.');
        return self::EXIT_FAILURE;
      }
      $verdict['images'] = (int) $options['images'];
    }
    if (self::option($options, 'usage-json') !== NULL) {
      $decoded = json_decode((string) $options['usage-json'], TRUE);
      if (!is_array($decoded)) {
        $this->logger()->error('--usage-json must be a JSON object.');
        return self::EXIT_FAILURE;
      }
      $verdict['usage'] = $decoded;
    }
    foreach (['resolved-model' => 'resolved_model', 'gateway-request-id' => 'gateway_request_id',
      'provider-request-id' => 'provider_request_id', 'note' => 'note'] as $option => $key) {
      if (self::option($options, $option) !== NULL) {
        $verdict[$key] = self::option($options, $option);
      }
    }

    try {
      $result = $this->reconcile->reconcile($attemptId, $verdict);
    }
    catch (LocalUsageException $e) {
      $this->logger()->error(sprintf('%s: %s', $e->usageCode, $e->getMessage()));
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(sprintf('Attempt %s reconciled as %s (observation revision %d).',
      $attemptId, $verdict['outcome'], $result['observation_revision']));
    $projection = $result['projection'];
    if ($projection === NULL) {
      $this->logger()->warning('The projection consumer is locked by another run; run xinshi-ai-usage:process to apply the verdict.');
      return self::EXIT_SUCCESS;
    }
    $this->io()->table(['Projection', 'Value'], [
      ['state', $projection['state']],
      ['dispatch_state', $projection['dispatch_state']],
      ['error_code', (string) $projection['error_code']],
      ['usage_revision', $projection['usage_revision']],
      ['usage_quality', (string) $projection['usage_quality']],
      ['images_generated', $projection['images_generated'] === NULL ? '' : $projection['images_generated']],
      ['cost', match ($projection['cost_valuation_state']) {
        NULL => 'none (nothing was sent)',
        'unpriced' => 'unpriced (' . $projection['cost_unpriced_reason'] . ')',
        default => $projection['cost_valuation_state'] . ' ' . $projection['cost_total_micros'] . ' micros',
      }],
    ]);
    return self::EXIT_SUCCESS;
  }

  private static function option(array $options, string $name): ?string {
    $value = $options[$name] ?? NULL;
    return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : NULL;
  }

}

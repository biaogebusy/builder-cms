<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Drush\Commands;

use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs and replays the usage projection consumer (UB1.6).
 */
final class UsageProjectionCommands extends DrushCommands {

  use AutowireTrait;

  public const PROCESS = 'xinshi-ai-usage:process';
  public const REBUILD = 'xinshi-ai-usage:rebuild';
  public const RELEASE = 'xinshi-ai-usage:release-quarantined';

  public function __construct(
    #[Autowire(service: 'xinshi_ai_usage.projection')]
    private readonly UsageProjectionService $projection,
  ) {
    parent::__construct();
  }

  /**
   * Applies pending usage events to the attempt projection and hourly rollup.
   */
  #[CLI\Command(name: self::PROCESS)]
  #[CLI\Option(name: 'site', description: 'Only process events of this site id; default is every site.')]
  #[CLI\Option(name: 'limit', description: 'Maximum events to apply in this run.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:process', description: 'Apply up to 200 pending events across all sites.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:process --site=example.com --limit=1000', description: 'Drain one site in a larger batch.')]
  public function process(array $options = ['site' => NULL, 'limit' => UsageProjectionService::DEFAULT_LIMIT]): int {
    $site = $this->site($options);
    $stats = $this->projection->process($site, (int) $options['limit']);
    return $this->report($stats, $site);
  }

  /**
   * Drops the projection of a site and rebuilds it from the stored events.
   */
  #[CLI\Command(name: self::REBUILD)]
  #[CLI\Option(name: 'site', description: 'The site id to rebuild (required).')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:rebuild --site=example.com', description: 'Replay every event of the site; cost entries are kept.')]
  public function rebuild(array $options = ['site' => NULL]): int {
    $site = $this->site($options);
    if ($site === NULL) {
      $this->logger()->error('--site is required for a rebuild.');
      return self::EXIT_FAILURE;
    }
    if (!$this->io()->confirm(sprintf('Drop the attempt projection, hourly rollup and watermark of "%s" and replay its events?', $site), FALSE)) {
      $this->logger()->warning('Cancelled.');
      return self::EXIT_FAILURE;
    }
    $stats = $this->projection->rebuild($site);
    return $this->report($stats, $site);
  }

  /**
   * Returns quarantined events to the queue after the underlying cause was fixed.
   */
  #[CLI\Command(name: self::RELEASE)]
  #[CLI\Option(name: 'site', description: 'Only release events of this site id; default is every site.')]
  #[CLI\Usage(name: 'drush xinshi-ai-usage:release-quarantined --site=example.com', description: 'Retry events that failed five times, then run process.')]
  public function releaseQuarantined(array $options = ['site' => NULL]): int {
    $site = $this->site($options);
    $released = $this->projection->releaseQuarantined($site);
    $this->logger()->success(sprintf('%d quarantined event(s) returned to the queue; run %s to apply them.', $released, self::PROCESS));
    return self::EXIT_SUCCESS;
  }

  private function site(array $options): ?string {
    $site = $options['site'] ?? NULL;
    return is_string($site) && trim($site) !== '' ? trim($site) : NULL;
  }

  private function report(array $stats, ?string $site): int {
    if ($stats['locked']) {
      $this->logger()->warning('Another consumer holds the projection lock; nothing was processed.');
      return self::EXIT_FAILURE_WITH_CLARITY;
    }
    $this->io()->table(['Metric', 'Value'], [
      ['claimed', $stats['claimed']],
      ['applied', $stats['applied']],
      ['audited', $stats['audited']],
      ['skipped', $stats['skipped']],
      ['failed', $stats['failed']],
      ['quarantined', $stats['quarantined']],
      ['remaining', $stats['remaining']],
    ]);
    if ($site !== NULL && ($watermark = $this->projection->watermark($site)) !== NULL) {
      $this->io()->text(sprintf('Watermark for %s: last_event_id=%d processed_count=%d data_as_of=%s',
        $site, $watermark['last_event_id'], $watermark['processed_count'],
        gmdate('Y-m-d\TH:i:s\Z', intdiv($watermark['data_as_of'], 1000))));
    }
    if ($stats['failed'] > 0 || $stats['quarantined'] > 0) {
      $this->logger()->error(sprintf('%d event(s) failed in this run and %d are quarantined; see the xinshi_ai_usage log channel, fix the cause, then run %s.',
        $stats['failed'], $stats['quarantined'], self::RELEASE));
      return self::EXIT_FAILURE_WITH_CLARITY;
    }
    $this->logger()->success('Projection is up to date.');
    return self::EXIT_SUCCESS;
  }

}

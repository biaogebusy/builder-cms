<?php

namespace Drupal\memcache_admin\EventSubscriber;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\memcache_admin\Event\MemcacheStatsEvent;
use Drupal\memcache_admin\Stats\MemcacheStatsObject;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds memcache server specific details to the stats array.
 */
class MemcacheServerStatsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MemcacheStatsEvent::BUILD_MEMCACHE_STATS][] = [
      'onPopulateStats',
      100
    ];
    $events[MemcacheStatsEvent::REPORT_MEMCACHE_STATS][] = [
      'onReportStats',
      100
    ];
    return $events;
  }

  /**
   * Populates the Memcache Server Stats
   *
   * @param \Drupal\memcache_admin\Event\MemcacheStatsEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onPopulateStats(MemcacheStatsEvent $event) {
    $raw_stats = $event->getRawStats();
    $bin = $event->getCacheBin();

    // No cache bin data, return.
    if (!isset($raw_stats[$bin])) {
      return;
    }
    // No servers found, return.
    if (!is_array($raw_stats[$bin])) {
      return;
    }
    $servers = array_keys($raw_stats[$bin]);
    $memcache_servers = [];
    foreach ($servers as $server) {
      // Memcache servers report libevent version, use that for detecting stats.
      if (isset($raw_stats[$bin][$server]['libevent'])) {
        $event->updateFormattedStats('memcache', $bin, $server, new MemcacheStatsObject($raw_stats[$bin][$server]));
        $event->updateServers($server);
      }
    }
    if (isset($raw_stats[$bin]['total'])) {
      $event->updateTotals([$bin => new MemcacheStatsObject($raw_stats[$bin]['total'])]);
    }
  }

  /**
   * Populates the reporting of a stored set of stats.
   *
   * @param \Drupal\memcache_admin\Event\MemcacheStatsEvent $event
   */
  public function onReportStats(MemcacheStatsEvent $event) {
    $stats = $event->getFormattedStats('memcache');
    $bin = $event->getCacheBin();

    // No cache bin data, return.
    if (empty($stats[$bin])) {
      // Failed to load statistics. Provide a useful error about where to get
      // more information and help.
      $this->messenger()->addError(
        $this->t(
          'There may be a problem with your Memcache configuration. Please review @readme for more information.',
          [
            '@readme' => 'README.txt',
          ]
        )
      );
      return;
    }
    // No servers found, return.
    if (!is_array($stats[$bin])) {
      return;
    }

    /**
     * @var string $server
     * @var MemcacheStatsObject $statistics
     */
    foreach ($stats[$bin] as $server => $statistics) {
      if (empty($statistics->getUptime())) {
        $this->messenger()
          ->addError($this->t('Failed to connect to server at :address.', [':address' => $server]));
      }
      else {
        $data['server_overview'][$server] = $this->t('v@version running @uptime', [
          '@version' => $statistics->getVersion(),
          '@uptime' => $statistics->getUptime()
        ]);
        $data['server_time'][$server] = $statistics->getServerTime();
        $data['server_connections'][$server] = $statistics->getConnections();
        $data['cache_sets'][$server] = $statistics->getSets();
        $data['cache_gets'][$server] = $statistics->getGets();
        $data['cache_counters'][$server] = $statistics->getCounters();
        $data['cache_transfer'][$server] = $statistics->getTransferred();
        $data['cache_average'][$server] = $statistics->getConnectionAvg();
        $data['memory_available'][$server] = $statistics->getMemory();
        $data['memory_evictions'][$server] = $statistics->getEvictions();
      }
    }

    // Build a custom report array.
    $report = [
      'uptime' => [
        'uptime' => [
          'label' => $this->t('Uptime'),
          'servers' => $data['server_overview'],
        ],
        'time' => [
          'label' => $this->t('Time'),
          'servers' => $data['server_time'],
        ],
        'connections' => [
          'label' => $this->t('Connections'),
          'servers' => $data['server_connections'],
        ],
      ],
      'stats' => [
        'sets' => [
          'label' => $this->t('Sets'),
          'servers' => $data["cache_sets"],
        ],
        'gets' => [
          'label' => $this->t('Gets'),
          'servers' => $data["cache_gets"],
        ],
        'counters' => [
          'label' => $this->t('Counters'),
          'servers' => $data["cache_counters"],
        ],
        'transfer' => [
          'label' => $this->t('Transferred'),
          'servers' => $data["cache_transfer"],
        ],
        'average' => [
          'label' => $this->t('Per-connection average'),
          'servers' => $data["cache_average"],
        ],
      ],
      'memory' => [
        'memory' => [
          'label' => $this->t('Available memory'),
          'servers' => $data['memory_available'],
        ],
        'evictions' => [
          'label' => $this->t('Evictions'),
          'servers' => $data['memory_evictions'],
        ],
      ],
    ];

    // Don't display aggregate totals if there's only one server.
    if (count($stats[$bin]) > 1) {
      /** @var MemcacheStatsObject $totals */
      $totals = $event->getTotals();
      $report['uptime']['uptime']['total'] = $this->t('n/a');
      $report['uptime']['time']['total'] = $this->t('n/a');
      $report['uptime']['connections']['total'] = $totals[$bin]->getConnections();
      $report['stats']['sets']['total'] = $totals[$bin]->getSets();
      $report['stats']['gets']['total'] = $totals[$bin]->getGets();
      $report['stats']['counters']['total'] = $totals[$bin]->getCounters();
      $report['stats']['transfer']['total'] = $totals[$bin]->getTransferred();
      $report['stats']['average']['total'] = $totals[$bin]->getConnectionAvg();
      $report['memory']['memory']['total'] = $totals[$bin]->getMemory();
      $report['memory']['evictions']['total'] = $totals[$bin]->getEvictions();
    }

    $event->updateReport($report);
  }
}

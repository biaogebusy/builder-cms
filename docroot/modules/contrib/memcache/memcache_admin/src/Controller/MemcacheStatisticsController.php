<?php

namespace Drupal\memcache_admin\Controller;

use Drupal\Component\Datetime\Time;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_admin\Event\MemcacheStatsEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Memcache Statistics.
 */
class MemcacheStatisticsController extends ControllerBase {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Memcache Driver Factory.
   * 
   * @var \Drupal\memcache\Driver\MemcacheDriverFactory
   */
  protected $memcacheDriverFactory;

  /**
   * Event Dispatcher Service.
   * 
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * Core Date Formatter Service.
   * 
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Core DateTime Service.
   * 
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder.
   */
  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, ContainerAwareEventDispatcher $dispatcher, DateFormatterInterface $dateFormatter, Time $time) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->dispatcher = $dispatcher;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('memcache.factory'),
      $container->get('event_dispatcher'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Callback for the Memcache Stats page.
   *
   * @param string $bin
   *   The bin name.
   *
   * @return array
   *   The page output.
   */
  public function statsTable($bin = 'default') {
    $bin = $this->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin, TRUE);

    // Instantiate our event.
    $event = new MemcacheStatsEvent($memcache, $bin);

    // Get the event_dispatcher service and dispatch the event.
    $this->dispatcher->dispatch($event, MemcacheStatsEvent::BUILD_MEMCACHE_STATS);

    // Report the PHP Memcache(d) driver version.
    if ($memcache->getMemcache() instanceof \Memcached) {
      $raw_stats['driver_version'] = $this->t('PECL Driver in Use: Memcached v@version', ['@version' => phpversion('Memcached')]);
    }
    elseif ($memcache->getMemcache() instanceof \Memcache) {
      $raw_stats['driver_version'] = $this->t('PECL Driver in Use: Memcache v@version', ['@version' => phpversion('Memcache')]);
    }

    // Get the event_dispatcher service and dispatch the event.
    $this->dispatcher->dispatch($event, MemcacheStatsEvent::REPORT_MEMCACHE_STATS);

    $output = ['#markup' => '<p>' . $raw_stats['driver_version']];
    $output[] = $this->statsTablesOutput($bin, $event->getServers(), $event->getReport());

    return $output;
  }

  /**
   * Callback for the Memcache Stats page.
   *
   * @param string $cluster
   *   The Memcache cluster name.
   * @param string $server
   *   The Memcache server name.
   * @param string $type
   *   The type of statistics to retrieve when using the Memcache extension.
   *
   * @return string
   *   The page output.
   */
  public function statsTableRaw($cluster, $server, $type = 'default') {
    $cluster = $this->binMapping($cluster);
    $server = str_replace('!', '/', $server);

    $slab = \Drupal::routeMatch()->getParameter('slab');
    $memcache = $this->memcacheDriverFactory->get($cluster, TRUE);
    if ($type == 'slabs' && !empty($slab)) {
      $stats = $memcache->stats($cluster, $slab, FALSE);
    }
    else {
      $stats = $memcache->stats($cluster, $type, FALSE);
    }

    // @codingStandardsIgnoreStart
    // @todo - breadcrumb
    // $breadcrumbs = [
    //   l(t('Home'), NULL),
    //   l(t('Administer'), 'admin'),
    //   l(t('Reports'), 'admin/reports'),
    //   l(t('Memcache'), 'admin/reports/memcache'),
    //   l(t($bin), "admin/reports/memcache/$bin"),
    // ];
    // if ($type == 'slabs' && arg(6) == 'cachedump' && user_access('access slab cachedump')) {
    //   $breadcrumbs[] = l($server, "admin/reports/memcache/$bin/$server");
    //   $breadcrumbs[] = l(t('slabs'), "admin/reports/memcache/$bin/$server/$type");
    // }
    // drupal_set_breadcrumb($breadcrumbs);
    // @codingStandardsIgnoreEnd
    if (isset($stats[$cluster][$server]) && is_array($stats[$cluster][$server]) && count($stats[$cluster][$server])) {
      $output = $this->statsTablesRawOutput($cluster, $server, $stats[$cluster][$server], $type);
    }
    elseif ($type == 'slabs' && is_array($stats[$cluster]) && count($stats[$cluster])) {
      $output = $this->statsTablesRawOutput($cluster, $server, $stats[$cluster], $type);
    }
    else {
      $output = $this->statsTablesRawOutput($cluster, $server, [], $type);
      $this->messenger()->addMessage($this->t('No @type statistics for this bin.', ['@type' => $type]));
    }

    return $output;
  }

  /**
   * Helper function, reverse map the memcache_bins variable.
   */
  private function binMapping($bin = 'cache') {
    $memcache      = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $bins = array_flip($memcache_bins);
    if (isset($bins[$bin])) {
      return $bins[$bin];
    }
    else {
      return $this->defaultBin($bin);
    }
  }

  /**
   * Statistics report: format total and open connections.
   */
  private function statsConnections($stats) {
    return $this->t(
      '@current open of @total total',
      [
        '@current' => number_format($stats['curr_connections']),
        '@total'   => number_format($stats['total_connections']),
      ]
    );
  }

  /**
   * Statistics report: calculate # of increments and decrements.
   */
  private function statsCounters($stats) {
    if (!is_array($stats)) {
      $stats = [];
    }

    $stats += [
      'incr_hits'   => 0,
      'incr_misses' => 0,
      'decr_hits'   => 0,
      'decr_misses' => 0,
    ];

    return $this->t(
      '@incr increments, @decr decrements',
      [
        '@incr' => number_format($stats['incr_hits'] + $stats['incr_misses']),
        '@decr' => number_format($stats['decr_hits'] + $stats['decr_misses']),
      ]
    );
  }

  /**
   * Generates render array for output.
   */
  private function statsTablesOutput($bin, $servers, $stats) {
    $memcache      = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $links = [];
    if (!is_array($servers)) {
      return;
    }
    foreach ($servers as $server) {

      // Convert socket file path so it works with an argument, this should
      // have no impact on non-socket configurations. Convert / to !.
      $links[] = Link::fromTextandUrl($server, Url::fromUri('base:/admin/reports/memcache/' . $memcache_bins[$bin] . '/' . str_replace('/', '!', $server)))->toString();
    }

    if (count($servers) > 1) {
      $headers = array_merge(['', $this->t('Totals')], $links);
    }
    else {
      $headers = array_merge([''], $links);
    }

    $output = [];
    foreach ($stats as $table => $data) {
      $rows = [];
      foreach ($data as $data_row) {
        $row = [];
        $row[] = $data_row['label'];
        if (isset($data_row['total'])) {
          $row[] = $data_row['total'];
        }
        foreach ($data_row['servers'] as $server) {
          $row[] = $server;
        }
        $rows[] = $row;
      }
      $output[$table] = [
        '#theme'  => 'table',
        '#header' => $headers,
        '#rows'   => $rows,

      ];
    }

    return $output;
  }

  /**
   * Generates render array for output.
   */
  private function statsTablesRawOutput($cluster, $server, $stats, $type) {
    $user          = \Drupal::currentUser();
    $current_type  = isset($type) ? $type : 'default';
    $memcache      = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();
    $bin           = isset($memcache_bins[$cluster]) ? $memcache_bins[$cluster] : 'default';
    $slab = \Drupal::routeMatch()->getParameter('slab');

    // Provide navigation for the various memcache stats types.
    $links = [];
    if (count($memcache->statsTypes())) {
      foreach ($memcache->statsTypes() as $type) {
        // @todo render array
        $link = Link::fromTextandUrl($type, Url::fromUri('base:/admin/reports/memcache/' . $bin . '/' . str_replace('/', '!', $server) . '/' . ($type == 'default' ? '' : $type)))->toString();
        if ($current_type == $type) {
          $links[] = '<strong>' . $link . '</strong>';
        }
        else {
          $links[] = $link;
        }
      }
    }
    $build = [
      'links' => [
        '#markup' => !empty($links) ? implode(' | ', $links) : '',
      ],
    ];

    $build['table'] = [
      '#type'  => 'table',
      '#header' => [
        $this->t('Property'),
        $this->t('Value'),
      ],
    ];

    $row = 0;

    // Items are returned as an array within an array within an array.  We step
    // in one level to properly display the contained statistics.
    if ($current_type == 'items' && isset($stats['items'])) {
      $stats = $stats['items'];
    }

    foreach ($stats as $key => $value) {

      // Add navigation for getting a cachedump of individual slabs.
      if (($current_type == 'slabs' || $current_type == 'items') && is_int($key) && $user->hasPermission('access slab cachedump')) {
        $build['table'][$row]['key'] = [
          '#type' => 'link',
          '#title' => $this->t('Slab @slab', ['@slab' => $key]),
          '#url' => Url::fromUri('base:/admin/reports/memcache/' . $bin . '/' . str_replace('/', '!', $server) . '/slabs/cachedump/' . $key),
        ];
      }
      else {
        $build['table'][$row]['key'] = ['#plain_text' => $key];
      }

      if (is_array($value)) {
        $subrow = 0;
        $build['table'][$row]['value'] = ['#type' => 'table'];
        foreach ($value as $k => $v) {

          // Format timestamp when viewing cachedump of individual slabs.
          if ($current_type == 'slabs' && $user->hasPermission('access slab cachedump') && !empty($slab) && $k == 0) {
            $k = $this->t('Size');
            $v = format_size($v);
          }
          elseif ($current_type == 'slabs' && $user->hasPermission('access slab cachedump') && !empty($slab) && $k == 1) {
            $k          = $this->t('Expire');
            $full_stats = $memcache->stats($cluster);
            $infinite   = $full_stats[$cluster][$server]['time'] - $full_stats[$cluster][$server]['uptime'];
            if ($v == $infinite) {
              $v = $this->t('infinite');
            }
            else {
              $v = $this->t('in @time', ['@time' => $this->dateFormatter->formatInterval($v - $this->time->getRequestTime())]);
            }
          }
          $build['table'][$row]['value'][$subrow] = [
            'key' => ['#plain_text' => $k],
            'value' => ['#plain_text' => $v],
          ];
          $subrow++;
        }
      }
      else {
        $build['table'][$row]['value'] = ['#plain_text' => $value];
      }
      $row++;
    }

    return $build;
  }

  /**
   * Helper function, reverse map the memcache_bins variable.
   */
  protected function getBinMapping($bin = 'cache') {
    $memcache      = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $bins = array_flip($memcache_bins);
    if (isset($bins[$bin])) {
      return $bins[$bin];
    }
    else {
      return $this->defaultBin($bin);
    }
  }

  /**
   * Helper function. Returns the bin name.
   */
  protected function defaultBin($bin) {
    if ($bin == 'default') {
      return 'cache';
    }

    return $bin;
  }

}

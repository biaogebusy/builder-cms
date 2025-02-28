<?php

namespace Drupal\memcache_admin\EventSubscriber;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\memcache_admin\Event\MemcacheStatsEvent;
use Drupal\memcache_admin\Stats\McrouterStatsObject;
use Drupal\memcache_admin\Stats\MemcacheStatsObject;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds memcache server specific details to the stats array.
 */
class McrouterStatsSubscriber implements EventSubscriberInterface {

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
    return $events;
  }

  /**
   * Adds stats to the memcache event.
   *
   * @param \Drupal\memcache_admin\Event\MemcacheStatsEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onPopulateStats(MemcacheStatsEvent $event) {
    $memcache = $event->getMemcache();
    if (!$memcache->getMemcache()->get('__mcrouter__.version')) {
      return;
    }

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
    foreach ($servers as $server) {
      if ($server =='total') {
        continue;
      }
      // McRouter reports num_servers use that for detecting stats.
      if (isset($raw_stats[$bin][$server]['num_servers'])) {
        $event->updateFormattedStats('memcache', $bin, $server, new McrouterStatsObject($raw_stats[$bin][$server]));
        $event->updateServers($server);
      }
    }
  }
}

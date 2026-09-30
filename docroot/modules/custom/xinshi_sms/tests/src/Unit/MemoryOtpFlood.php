<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Core\Flood\FloodInterface;

/**
 * Controlled sliding-window storage shared by multiple service instances.
 */
final class MemoryOtpFlood implements FloodInterface {

  public array $events = [];
  public int $now = 1000;

  public function register($name, $window = 3600, $identifier = NULL) {
    $this->events[$name][$identifier][] = $this->now;
  }

  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    return count(array_filter($this->events[$name][$identifier] ?? [],
      fn($time) => $time > $this->now - $window)) < $threshold;
  }

  public function clear($name, $identifier = NULL) {
    unset($this->events[$name][$identifier]);
  }

  public function garbageCollection() {}

}

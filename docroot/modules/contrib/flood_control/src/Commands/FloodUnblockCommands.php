<?php

namespace Drupal\flood_control\Commands;

use Drupal\flood_control\FloodUnblockManager;
use Drush\Commands\DrushCommands;

/**
 * Flood unblock. module's Drush 9 commands.
 */
class FloodUnblockCommands extends DrushCommands {

  /**
   * The FloodUnblockManager service.
   *
   * @var \Drupal\flood_control\FloodUnblockManager
   */
  private $manager;

  /**
   * FloodUnblockCommands constructor.
   *
   * @param \Drupal\flood_control\FloodUnblockManager $manager
   *   The FloodUnblockManager service.
   */
  public function __construct(FloodUnblockManager $manager) {
    $this->manager = $manager;
  }

  /**
   * Clears the floods based on IP.
   *
   * @param string $ip
   *   IP address to clear.
   *
   * @command flood_unblock:ip
   * @usage flood_unblock:ip ip_address
   */
  public function unblockIp($ip) {
    $events = $this->manager->getEvents();
    foreach ($events as $key => $event) {
      $fids = $this->manager->getEventIds($key, $ip);
      foreach ($fids as $fid) {
        $this->manager->floodUnblockClearEvent($fid);
      }
    }
    $this->output()->writeln("Cleared the events for IP address $ip");
  }

  /**
   * Clears all floods in the system.
   *
   * @command flood_unblock:all
   * @usage flood_unblock:all
   */
  public function unblockAll() {
    $events = $this->manager->getEvents();
    foreach ($events as $key => $event) {
      $fids = $this->manager->getEventIds($key);
      foreach ($fids as $fid) {
        $this->manager->floodUnblockClearEvent($fid);
      }
      $label = $event['label'];
      $this->output()->writeln("Cleared the {$label} events");
    }
  }

}

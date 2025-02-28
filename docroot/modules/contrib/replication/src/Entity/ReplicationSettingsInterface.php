<?php

namespace Drupal\replication\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for replication settings entities.
 */
interface ReplicationSettingsInterface extends ConfigEntityInterface {

  /**
   * Get the plugin ID of the replication filter to use.
   *
   * @return string
   */
  public function getFilterId();

  /**
   * Get the replication filter parameters.
   *
   * @return array
   */
  public function getParameters();

}

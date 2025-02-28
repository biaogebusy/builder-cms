<?php

namespace Drupal\replication;

use Drupal\multiversion\Entity\WorkspaceInterface;

interface ChangesFactoryInterface {

  /**
   * Constructs a new Changes instance.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *
   * @return \Drupal\replication\Changes\ChangesInterface
   */
  public function get(WorkspaceInterface $workspace);

}

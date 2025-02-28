<?php

namespace Drupal\replication;

use Drupal\multiversion\Entity\WorkspaceInterface;

interface RevisionDiffFactoryInterface {

  /**
   * Constructs a new RevisionDiff instance.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *
   * @return \Drupal\replication\RevisionDiff\RevisionDiff
   */
  public function get(WorkspaceInterface $workspace);

}

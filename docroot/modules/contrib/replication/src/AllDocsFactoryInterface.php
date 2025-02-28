<?php

namespace Drupal\replication;

use Drupal\multiversion\Entity\WorkspaceInterface;

interface AllDocsFactoryInterface {

  /**
   * Constructs a new AllDocs instance.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *
   * @return \Drupal\replication\AllDocs\AllDocsInterface
   */
  public function get(WorkspaceInterface $workspace);

}

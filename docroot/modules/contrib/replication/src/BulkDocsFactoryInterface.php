<?php

namespace Drupal\replication;

use Drupal\multiversion\Entity\WorkspaceInterface;

interface BulkDocsFactoryInterface {

  /**
   * Constructs a new BulkDocs instance.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *
   * @return \Drupal\replication\BulkDocs\BulkDocsInterface
   */
  public function get(WorkspaceInterface $workspace);

}

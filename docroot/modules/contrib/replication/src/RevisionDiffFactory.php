<?php

namespace Drupal\replication;

use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\replication\RevisionDiff\RevisionDiff;

class RevisionDiffFactory implements RevisionDiffFactoryInterface {

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface
   */
  protected $revIndex;

  /**
   * @var \Drupal\replication\RevisionDiff\RevisionDiffInterface[]
   */
  protected $instances = [];

  /**
   * @param \Drupal\multiversion\Entity\Index\RevisionIndexInterface $rev_index
   */
  public function __construct(RevisionIndexInterface $rev_index) {
    $this->revIndex = $rev_index;
  }

  /**
   * @inheritDoc
   */
  public function get(WorkspaceInterface $workspace) {
    if (!isset($this->instances[$workspace->id()])) {
      $this->instances[$workspace->id()] = new RevisionDiff(
        $this->revIndex,
        $workspace
      );
    }
    return $this->instances[$workspace->id()];
  }

}

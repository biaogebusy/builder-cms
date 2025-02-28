<?php

namespace Drupal\replication\RevisionDiff;

use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;

class RevisionDiff implements RevisionDiffInterface {

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface
   */
  protected $revIndex = [];

  /**
   * @var string[]
   */
  protected $revs;

  /**
   * @param \Drupal\multiversion\Entity\Index\RevisionIndexInterface $rev_index
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   */
  public function __construct(RevisionIndexInterface $rev_index, WorkspaceInterface $workspace) {
    $this->revIndex = $rev_index;
    $this->workspaceId = $workspace->id();
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionIds(array $revs) {
    $this->revs = $revs;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionIds() {
    return $this->revs;
  }

  /**
   * {@inheritdoc}
   *
   * @todo {@link https://www.drupal.org/node/2344005 Implement the
   *   possible_ancestors key.}
   */
  public function getMissing() {
    $missing = [];
    foreach ($this->getRevisionIds() as $uuid => $revs) {
      $keys = [];
      foreach ($revs as $rev) {
        $keys[] = "$uuid:$rev";
      }
      $existing = $this->revIndex->useWorkspace($this->workspaceId)->getMultiple($keys);
      foreach ($revs as $rev) {
        if (!isset($existing["$uuid:$rev"])) {
          $missing[$uuid]['missing'][] = $rev;
        }
      }
    }
    return $missing;
  }

}

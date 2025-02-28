<?php

namespace Drupal\replication\RevisionDiff;

interface RevisionDiffInterface {

  /**
   * @param array $revision_ids
   */
  public function setRevisionIds(array $revision_ids);

  /**
   * @return array
   */
  public function getRevisionIds();

  /**
   * Returns missing revisions ids.
   * @return array
   */
  public function getMissing();

}

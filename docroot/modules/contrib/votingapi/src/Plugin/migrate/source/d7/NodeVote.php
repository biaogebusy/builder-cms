<?php

namespace Drupal\votingapi\Plugin\migrate\source\d7;

/**
 * Drupal 7 vote source from database.
 *
 * @MigrateSource(
 *   id = "d7_node_vote",
 *   source_module = "votingapi"
 * )
 */
class NodeVote extends Vote {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    if (!empty($this->configuration['node_type'])) {
      $query->leftJoin('node', 'n', 'n.nid = v.entity_id');
      $query->condition('v.entity_type', 'node');
      $query->condition('n.type', (array) $this->configuration['node_type'], 'IN');
    }
    return $query;
  }

}

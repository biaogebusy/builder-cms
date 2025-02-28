<?php

namespace Drupal\votingapi\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Vote type migration source plugin.
 *
 * @MigrateSource(
 *   id = "d7_vote_type",
 *   source_module = "votingapi"
 * )
 */
class VoteType extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'tag' => 'Tag Name',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'tag' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('votingapi_vote', 'vv')
      ->distinct()
      ->fields('vv', ['tag'])
      ->orderBy('tag');
  }

}

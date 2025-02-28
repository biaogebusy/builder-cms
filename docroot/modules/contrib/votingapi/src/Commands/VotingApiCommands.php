<?php

namespace Drupal\votingapi\Commands;

use Drush\Commands\DrushCommands;
use Drupal\votingapi\Entity\Vote;

/**
 * Drush 9+ commands for the Voting API module.
 *
 * Generates Voting API votes, recalculates results for existing votes, or
 * flushes Voting API data entirely.
 */
class VotingApiCommands extends DrushCommands {

  /**
   * Creates dummy voting data.
   *
   * @param string $entity_type
   *   The type of entity to generate votes for.
   * @param string $vote_type
   *   The type of votes to generate, defaults to 'percent'.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @command voting:generate
   * @aliases genv,generate-votes
   *
   * @option kill_votes
   *   Specify 'kill_votes' to delete all existing votes before generating
   *   new ones.
   * @option age
   *   The maximum age, in seconds, of each vote.
   * @option node_types
   *   A comma delimited list of node types to generate votes for, if the entity
   *   type is 'node'.
   *
   * @usage drush voting:generate [entity_type]
   *   Creates dummy voting data for the specified entity type.
   */
  public function votes($entity_type, $vote_type, array $options = []) {
    $options += [
      'kill_votes' => NULL,
      'age' => NULL,
      'node_types' => NULL,
    ];

    $this->generateVotes($entity_type, $vote_type, $options);

    $this->logger->success(dt('Generated @vtype votes for @etype entities.', [
      '@vtype' => $vote_type,
      '@etype' => $entity_type,
    ]));
  }

  /**
   * Regenerates voting results from raw vote data.
   *
   * @param string $entity_type
   *   The type of entity to recalculate vote results for.
   * @param string $entity_id
   *   The ID of the entity.
   * @param string $vote_type
   *   The type of votes to generate, defaults to 'percent'.
   *
   * @command voting:recalculate
   * @aliases vcalc,votingapi-recalculate
   *
   * @usage drush voting:recalculate [entity_type]
   *  Regenerates voting results from raw vote data. Defaults to 'node'.
   */
  public function recalculate($entity_type = 'node', $entity_id = NULL, $vote_type = 'vote') {
    // Prep some starter query objects.
    if (empty($entity_id)) {
      $votes = \Drupal::database()->select('votingapi_vote', 'vv')
        ->fields('vv', ['entity_type', 'entity_id'])
        ->condition('entity_type', $entity_type, '=')
        ->distinct(TRUE)
        ->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $message = dt('Rebuilt voting results for @type votes.', ['@type' => $entity_type]);
    }
    else {
      $votes[] = ['entity_type' => $entity_type, 'entity_id' => $entity_id];
      $message = dt('Rebuilt voting results for @type id: @entity_id.', [
        '@type' => $entity_type,
        '@entity_id' => $entity_id,
      ]);
    }

    $manager = \Drupal::service('plugin.manager.votingapi.resultfunction');
    foreach ($votes as $vote) {
      $manager->recalculateResults($vote['entity_type'], $vote['entity_id'], $vote_type);
    }

    $this->logger->success($message);
  }

  /**
   * Deletes all existing voting data.
   *
   * @param string $entity_type
   *   The type of entity whose voting data should be flushed.
   * @param string $entity_id
   *   The ID of the entity.
   *
   * @command voting:flush
   * @aliases vflush,votingapi-flush
   *
   * @usage drush voting:flush [entity_type | 'all']
   *  Deletes all existing voting data for the specified entity type.
   */
  public function flush($entity_type = 'all', $entity_id = NULL) {
    if ($this->io()->confirm(dt("Delete @type voting data?", ['@type' => $entity_type]))) {
      $cache = \Drupal::database()->delete('votingapi_result');
      $votes = \Drupal::database()->delete('votingapi_vote');

      if ($entity_type !== 'all') {
        $cache->condition('entity_type', $entity_type);
        $votes->condition('entity_type', $entity_type);
      }
      if (!empty($entity_id)) {
        $cache->condition('entity_id', $entity_id);
        $votes->condition('entity_id', $entity_id);
      }

      $cache->execute();
      $votes->execute();

      $this->logger->success(dt('Flushed vote data for @type entities.', ['@type' => $entity_type]));
    }
  }

  /**
   * Utility method to generate votes.
   *
   * @param string $entity_type
   *   The type of entity to generate votes for.
   * @param string $vote_type
   *   The type of votes to generate, defaults to 'percent'.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   */
  protected function generateVotes($entity_type = 'node', $vote_type = 'percent', array $options = []) {
    $options += [
      'age' => 36000,
      'node_types' => [],
      'kill_votes' => FALSE,
    ];
    if (!empty($options['kill_votes'])) {
      $cache = \Drupal::database()->delete('votingapi_result')
        ->condition('entity_type', $entity_type)
        ->execute();
      $votes = \Drupal::database()->delete('votingapi_vote')
        ->condition('entity_type', $entity_type)
        ->execute();
    }
    $uids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->execute();
    $query = \Drupal::database()->select($entity_type, 'e')
      ->fields('e', ['nid']);
    if ($entity_type == 'node' && !empty($options['types'])) {
      $query->condition('e.type', $options['types'], 'IN');
    }
    $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($results as $entity) {
      $this->castVotes($entity_type, $entity['nid'], $options['age'], $uids, $vote_type);
    }
  }

  /**
   * Utility method to generate votes on a node by a set of users.
   */
  protected function castVotes($entity_type, $entity_id, $timestamp = 0, array $uids = [], $style = 'percent') {
    foreach ($uids as $uid) {
      $request_time = \Drupal::time()->getRequestTime();
      $value = $style === 'points' ? rand(0, 1) ? 1 : -1 : mt_rand(1, 5) * 20;
      $vote = Vote::create(['type' => 'vote']);
      $vote->setVotedEntityId($entity_id);
      $vote->setVotedEntityType($entity_type);
      $vote->setOwnerId($uid);
      $vote->setCreatedTime($request_time - mt_rand(0, $timestamp));
      $vote->setValueType($style);
      $vote->setValue($value);
      $vote->save();
    }
  }

}

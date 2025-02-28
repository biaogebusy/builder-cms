<?php

/**
 * @file
 * Contains votingapi_tokens.tokens.inc.
 *
 * Add support for vote tokens on entities.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function votingapi_tokens_token_info() {
  $entity_types = _votingapi_tokens_get_entity_types();

  $types = [];
  $tokens = [];
  foreach ($entity_types as $entity_type) {
    $types['votingapi_' . $entity_type . '_token'] = [
      'name' => t('VotingAPI tokens'),
      'description' => t('Dynamic Tokens for VotingAPI.'),
      'needs-data' => $entity_type,
    ];

    $tokens['votingapi_' . $entity_type . '_token']['vote_count'] = [
      'name' => t('Vote count'),
      'dynamic' => TRUE,
      'description' => t('Number of votes.'),
    ];
    $tokens['votingapi_' . $entity_type . '_token']['vote_average'] = [
      'name' => t('Average Result'),
      'dynamic' => TRUE,
      'description' => t('Average result of votes.'),
    ];
    $tokens['votingapi_' . $entity_type . '_token']['best_vote'] = [
      'name' => t('Best Vote'),
      'dynamic' => TRUE,
      'description' => t('Best Vote cast.'),
    ];
    $tokens['votingapi_' . $entity_type . '_token']['worst_vote'] = [
      'name' => t('Worst Vote'),
      'dynamic' => TRUE,
      'description' => t('Worst Vote cast.'),
    ];
  }

  return [
    'types' => $types,
    'tokens' => $tokens,
  ];
}

/**
 * Implements hook_tokens().
 */
function votingapi_tokens_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  // $entity_types = array_keys(\Drupal::entityTypeManager()->getDefinitions());
  $entity_types = _votingapi_tokens_get_entity_types();
  foreach ($entity_types as $entity_type) {
    if ($type == 'votingapi_' . $entity_type . '_token' && !empty($data[$entity_type])) {
      $votes = \Drupal::service('plugin.manager.votingapi.resultfunction')
        ->getResults($entity_type, $data[$entity_type]->id());
      foreach ($tokens as $name => $original) {
        list($token_name, $vote_type) = explode(':', $name);
        switch ($token_name) {
          case 'vote_count':
            $replacements[$original] = $votes[$vote_type]['vote_count'];
            break;

          case 'vote_average':
            $replacements[$original] = $votes[$vote_type]['vote_average'];
            break;

          case 'best_vote':
            $replacements[$original] = max(_votingapi_tokens_get_votes_per_entity($entity_type, $data[$entity_type]->id(), $vote_type));
            break;

          case 'worst_vote':
            $replacements[$original] = min(_votingapi_tokens_get_votes_per_entity($entity_type, $data[$entity_type]->id(), $vote_type));
            break;
        }
      }
    }
  }

  return $replacements;
}

/**
 * List of entity-types with field type voting_api_field.
 *
 * @return array
 *   Entity types with field type voting_api_field.
 */
function _votingapi_tokens_get_entity_types() {
  $entity_list = [];
  $database = \Drupal::database();
  $query = $database->query("SELECT DISTINCT entity_type FROM {votingapi_vote}");
  $entity_list = array_map(
    function ($v) {
      return $v->entity_type;
    },
    $query->fetchAll()
  );
  return $entity_list;
}

/**
 * Get votes of vote_type on entity.
 *
 * @param string $entity_type_id
 *   Entity type id.
 * @param string $entity_id
 *   Entity id.
 * @param string $vote_type
 *   Vote type.
 *
 * @return array
 *   Votes of vote_type on entity.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 */
function _votingapi_tokens_get_votes_per_entity($entity_type_id, $entity_id, $vote_type) {
  $vote_ids = \Drupal::entityQuery('vote')
    ->condition('entity_type', $entity_type_id)
    ->condition('entity_id', $entity_id)
    ->condition('type', $vote_type)
    ->accessCheck(TRUE)
    ->execute();
  $vote_storage = \Drupal::entityTypeManager()->getStorage('vote');
  $values = [];
  if (!empty($vote_ids)) {
    foreach ($vote_ids as $vote_id) {
      $vote = $vote_storage->load($vote_id);
      $values[] = $vote->getValue();
    }
  }
  return $values;
}

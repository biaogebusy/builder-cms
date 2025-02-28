<?php

/**
 * @file
 * Provides hook documentation for the VotingAPI module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the information provided in \Drupal\votingapi\Annotation\VoteResult.
 *
 * @param array $results
 *   The array of vote results, keyed on the machine-readable name.
 */
function hook_vote_result_info_alter(&$results) {
  // Override the Voting API module's 'Count' vote result label.
  $results['count']['label'] = t('All the things');
}

/**
 * @} End of "addtogroup hooks".
 */

/**
 * Adds to or changes the calculated vote results for an entity.
 *
 * VotingAPI calculates a number of common aggregate functions automatically,
 * including the average vote and total number of votes cast.
 *
 * @param array $vote_results
 *   An alterable array of aggregate vote results.
 * @param string $entity_type
 *   A string identifying the type of entity being rated. Node, comment,
 *   aggregator item, etc.
 * @param int $entity_id
 *   The key ID of the entity being rated.
 *
 * @see VoteResultFunctionManager::recalculateResults()
 */
function hook_votingapi_results_alter(array &$vote_results, $entity_type, $entity_id) {
  // Calculate a standard deviation for votes cast on an entity.
  $query = Database::getConnection()->select('votingapi_vote', 'v');
  $query->addExpression('STDDEV(v.value)', 'standard_deviation');
  $query->condition('v.entity_type', $entity_type);
  $query->condition('v.entity_id', $entity_id);
  $query->groupBy('v.tag');

  $aggregate = $query->execute()->fetchObject();

  // Add the standard deviation to the voted entity results.
  $vote_results[] = [
    'entity_id' => $entity_id,
    'entity_type' => $entity_type,
    'type' => $vote_results[0]->bundle(),
    'function' => 'standard_deviation',
    'value' => $aggregate->standard_deviation,
    'value_type' => $vote_results[0]->get('value_type')->value,
    'timestamp' => \Drupal::time()->getRequestTime(),
  ];
}

/**
 * Allows altering metadata describing Voting tags, value_types, and functions.
 *
 * If your module uses custom tags or value_types, or calculates custom
 * aggregate functions, please implement this hook so other modules can properly
 * interperet and display your data.
 *
 * Three major bins of data are stored: tags, value_types, and aggregate result
 * functions. Each entry in these bins is keyed by the value stored in the
 * actual Voting API tables, and contains an array with (minimally) 'name' and
 * 'description' keys. Modules can add extra keys to their entries if desired.
 *
 * @param array $data
 *   An alterable array of aggregate vote results.
 *
 * @see votingapi_metadata()
 */
function hook_votingapi_metadata_alter(&$data) {
  // Document several custom tags for rating restaurants and meals.
  $data['tags']['bread'] = [
    'name' => t('Bread'),
    'description' => t('The quality of the food at a restaurant.'),
    'module' => 'mymodule',
    // This is optional; we can add it for our own purposes.
  ];
  $data['tags']['circuses'] = [
    'name' => t('Circuses'),
    'description' => t('The quality of the presentation and atmosphere at a restaurant.'),
    'module' => 'mymodule',
  ];

  // Document two custom aggregate function.
  $data['functions']['standard_deviation'] = [
    'name' => t('Standard deviation'),
    'description' => t('The standard deviation of all votes cast on a given piece of content. Use this to find controversial content.'),
    'module' => 'mymodule',
  ];
  $data['functions']['median'] = [
    'name' => t('Median vote'),
    'description' => t('The median vote value cast on a given piece of content. More accurate than a pure average when there are a few outlying votes.'),
    'module' => 'mymodule',
  ];
}

/**
 * Declares callback functions for formatting a Voting API Views field.
 *
 * Loads all votes for a given piece of content, then calculates and caches the
 * aggregate vote results. This is only intended for modules that have assumed
 * responsibility for the full voting cycle: the votingapi_set_vote() function
 * recalculates automatically.
 *
 * @param mixed $field
 *   A Views field object. This can be used to expose formatters only for tags,
 *   vote values, aggregate functions, etc.
 *
 * @return array
 *   An array of key-value pairs, in which each key is a callback function and
 *   each value is a human-readable description of the formatter.
 *
 * @see votingapi_set_votes()
 */
function hook_votingapi_views_formatters($field) {
  if ($field->field == 'value') {
    return ['mymodule_funky_formatter' => t('MyModule value formatter')];
  }
  if ($field->field == 'tag') {
    return ['mymodule_funky_tags' => t('MyModule tag formatter')];
  }
}

/**
 * Voting API's vote storage can be overridden by setting the
 * 'votingapi_vote_storage' state variable to an alternative class.
 */
\Drupal::state()->set('votingapi_vote_storage', 'Mongodb_VoteStorage');

/**
 *
 */
class Mongodb_VoteStorage {

  /**
   * Save a vote in the database.
   *
   * @param $vote
   *   instance of VotingApi_Vote.
   */
  public function addVote(&$vote) {
    mongodb_collection('votingapi_vote')->insert($vote);
  }

  /**
   * Deletes votes from the database.
   *
   * @param $votes
   *   An array of VotingApi_Vote instances to delete.
   *   Minimally, each vote must have the 'vote_id' key set.
   * @param $vids
   *   A list of the 'vote_id' values from $votes.
   */
  public function deleteVotes($votes, $vids) {
    mongodb_collection('votingapi_vote')->delete(['vote_id' => ['$in' => array_map('intval', $vids)]]);
  }

  /**
   * Select individual votes from the database.
   *
   * @param $criteria
   *   instance of VotingApi_Criteria.
   * @param $limit
   *   An integer specifying the maximum number of votes to return. 0 means
   *   unlimited and is the default.
   *
   * @return
   *   An array of VotingApi_Vote objects matching the criteria.
   */
  public function selectVotes($criteria, $limit) {
    $find = [];
    foreach ($criteria as $key => $value) {
      $find[$key] = is_array($value) ? ['$in' => $value] : $value;
    }
    $cursor = mongodb_collection('votingapi_vote')->find($find);
    if (!empty($limit)) {
      $cursor->limit($limit);
    }
    $votes = [];
    foreach ($cursor as $vote) {
      $votes[] = $vote;
    }
    return $votes;
  }

  /**
   * TODO.
   */
  public function standardResults($entity_id, $entity) {
    // TODO.
  }

}

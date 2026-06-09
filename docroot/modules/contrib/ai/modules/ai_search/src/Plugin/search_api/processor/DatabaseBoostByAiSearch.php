<?php

namespace Drupal\ai_search\Plugin\search_api\processor;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Prepend and combine AI Search results into the database search.
 *
 * @SearchApiProcessor(
 *   id = "database_boost_by_ai_search",
 *   label = @Translation("Boost Database by AI Search"),
 *   description = @Translation("This combines results from the AI Search (Vector Database) with the traditional database, both finding results that would otherwise not be found due to lack of keyword match, as well as vastly improving the relevance of the top results. It prepends the results from the AI Search into the database results ready for and respecting any filtering applied to this index (such as filters, exposed filters, or facets)."),
 *   stages = {
 *     "preprocess_query" = 0,
 *   }
 * )
 */
class DatabaseBoostByAiSearch extends BoostByAiSearchBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    if ($index->getServerInstance()->getBackendId() == 'search_api_db') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    if ($this->index->getServerId() && $server = Server::load($this->index->getServerId())) {
      if ($server->getBackendId() !== 'search_api_db') {
        $form_state->setErrorByName('search_api_ai_index', $this->t('This processor plugin only supports "search_api_db", but the backend of this index is "@backend"', [
          '@backend' => $server->getBackendId(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    parent::preprocessSearchQuery($query);

    // Only do something if we have search terms. It is possible that the
    // index is being filtered only without any terms, in which case we have
    // nothing more to do.
    if ($query_string_keys = $query->getKeys()) {
      $ai_results = $this->getAiSearchResults($query_string_keys);
      if ($ai_results) {
        if ($languages = $query->getLanguages()) {
          $ai_results = $this->normalizeLanguage($ai_results, $languages);
        }
        $query->addTag('database_boost_by_ai_search');
        $query->addTag('ai_search_ids:' . implode(',', array_keys($ai_results)));
      }
    }
  }

  /**
   * The vector database AI search results may be in any language.
   *
   * These need to be normalized to allowed languages. This happens because the
   * semantic meaning of the words are the same roughly the same regardless of
   * which language they are said in.
   *
   * @param array $ai_results
   *   The AI results to be updated.
   * @param array $allowed_languages
   *   Get any language restrictions on the query.
   *
   * @return array
   *   The updated results.
   */
  protected function normalizeLanguage(array $ai_results, array $allowed_languages): array {
    $updated_results = [];

    // If there is no match, determine what language to default to.
    $default_language = 'en';
    if (!in_array('en', $allowed_languages)) {
      $default_language = reset($allowed_languages);
    }
    foreach ($ai_results as $key => $result) {

      // We expect results like "entity:node/1:es".
      $parts = explode(':', $key);
      if (count($parts) !== 3) {
        $updated_results[$key] = $result;
        continue;
      }

      // Only use results in the allowed languages.
      $result_language = $parts[2];
      if (!in_array($result_language, $allowed_languages)) {
        $parts[2] = $default_language;
      }
      $key = implode(':', $parts);
      $updated_results[$key] = $result;
    }
    return $updated_results;
  }

  /**
   * Alter the database query.
   *
   * This method is called from the hook_query_TAG_alter() function found in
   * ai_search.module.
   *
   * @param \Drupal\Core\Database\Query\AlterableInterface $query
   *   The database query.
   */
  public static function queryAlter(AlterableInterface $query) {

    // The 'search_api_db' tag is added in
    // Drupal\search_api_db\Plugin\search_api\backend\Database::createDbQuery()
    // which is passed the Search API Query itself as metadata.
    $search_api_query = $query->getMetaData('search_api_query');
    if (
      $query instanceof SelectInterface
      && $search_api_query instanceof QueryInterface
    ) {

      // Extract IDs from the query tag added in
      // DatabaseBoostByAiSearch::preprocessSearchQuery().
      $item_ids = [];
      $tags = $search_api_query->getTags();
      if (!empty($tags)) {
        foreach ($tags as $tag) {
          if (str_starts_with($tag, 'ai_search_ids:')) {
            $tag = str_replace('ai_search_ids:', '', $tag);
            $item_ids = explode(',', $tag);
          }
        }
      }

      // If we have entity IDs, alter the query.
      if ($item_ids) {

        // Update conditions of the base query.
        self::updateConditions($query, $item_ids);

        // Update conditions of the joined queries if existing.
        $tables = &$query->getTables();
        foreach ($tables as &$table) {
          if (
            !is_array($table)
            || !isset($table['table'])
            || !$table['table'] instanceof SelectInterface
          ) {
            continue;
          }
          $table['table'] = self::updateConditions(
            $table['table'],
            $item_ids,
            $table['alias'],
          );
        }

        // Having conditions do not support nested OR in the Select Interface,
        // but as far as can be seen, there is always only one, so we can just
        // add it in. If there are no conditions, just skip this.
        $having_conditions = &$query->havingConditions();
        if (count($having_conditions) >= 2) {
          $having_conditions['#conjunction'] = 'OR';
          $query->having('t.item_id IN (:item_ids[])', [
            ':item_ids[]' => $item_ids,
          ]);
        }

        // The updated query conditions can lead to multiple rows returned per
        // item ID. E.g. if we have 10 results for a single ID and only 10
        // results are allowed, we would get a single result without this.
        $query->groupBy('t.item_id');

        // Add an expression to boost AI result entity IDs.
        // Ensure the entity IDs are all integers.
        $placeholders = [];
        $expression_parts = [];
        $total = count($item_ids);
        foreach ($item_ids as $key => $item_id) {
          $expression_parts[] = 'WHEN t.item_id = :entity_' . $key . ' THEN ' . ($total - $key);
          $placeholders[':entity_' . $key] = $item_id;
        }
        $expression = 'CASE ' . implode(' ', $expression_parts) . ' ELSE 0 END';
        $query->addExpression($expression, 'ai_boost', $placeholders);

        // Sort by the ai_boost field first, followed by this index's score,
        // so AI results appear first.
        $order_by_parts =& $query->getOrderBy();
        $new_order = ['ai_boost' => 'DESC'] + $order_by_parts;
        $order_by_parts = $new_order;
      }
    }
  }

  /**
   * Update conditions (or nested conditions) for vector database results.
   *
   * By default, only content where the searched terms exist in the results
   * will be returned, but we also want results that have similar terms, not
   * just exact terms, so we allow results that either are in the vector
   * database returned IDs OR have the keywords entered.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query to check for keywords and update as appropriate.
   * @param array $item_ids
   *   The search API item IDs returned by the vector database.
   * @param string $alias
   *   The alias of the table being checked.
   */
  protected static function updateConditions(
    SelectInterface $query,
    array $item_ids,
    string $alias = 't',
  ): SelectInterface {
    $conditions = &$query->conditions();
    $keyword_condition = FALSE;
    foreach ($conditions as $key => $condition) {

      // Get the full keyword search condition.
      if (
        is_array($condition)
        && isset($condition['field'])
        && $condition['field'] === $alias . '.word'
        && isset($condition['value'])
        && isset($condition['operator'])
      ) {
        $keyword_condition = $condition;
        unset($conditions[$key]);
      }
    }
    if ($keyword_condition) {

      // Add the condition group back in, but also add the IDs which
      // may not have the same keywords since vector search finds words
      // with similar meanings.
      $condition_group = $query->orConditionGroup();
      $condition_group->condition(
        $keyword_condition['field'],
        $keyword_condition['value'],
        $keyword_condition['operator'],
      );
      $condition_group->condition(
        $alias . '.item_id',
        $item_ids,
        'IN',
      );
      $query->condition($condition_group);
    }
    return $query;
  }

}

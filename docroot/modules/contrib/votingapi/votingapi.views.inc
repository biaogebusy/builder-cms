<?php

/**
 * @file
 * Views data alterations for the votingapi module.
 */

use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Implements hook_views_data_alter().
 */
function votingapi_views_data_alter(&$data) {
  $result_function_manager = \Drupal::service('plugin.manager.votingapi.resultfunction');
  $result_functions = $result_function_manager->getDefinitions();
  if (empty($result_functions)) {
    return;
  }

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager */
  $entity_manager = \Drupal::service('entity_type.manager');
  try {
    $vote_type_storage = $entity_manager->getStorage('vote_type');
  }
  catch (PluginNotFoundException $e) {
    return;
  }

  $vote_types = $vote_type_storage->loadMultiple();
  if (empty($vote_types)) {
    return;
  }

  // Find entity types suitable for vote results views data.
  $views_entity_types = [];
  $entity_types = $entity_manager->getDefinitions();

  foreach ($entity_types as $entity_type_id => $entity_type) {
    // Exclude votes.
    if ($entity_type_id == 'vote_result' || $entity_type_id == 'vote' || $entity_type->getBundleOf() == 'vote') {
      continue;
    }

    // Limit to content entity types.
    if ($entity_type->getGroup() == 'content' &&
        $entity_type->isTranslatable() &&
        $entity_type->getKey('id')) {
      $views_entity_types[$entity_type_id] = $entity_type;
    }
  }

  // Add views data for each entity type.
  if ($views_entity_types) {
    foreach ($views_entity_types as $entity_type_id => $entity_type) {
      $data_table_name = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
      if (empty($data_table_name) || !isset($data[$data_table_name])) {
        continue;
      }

      $id_key = $entity_type->getKey('id');
      $tokens = [
        '@entity_type' => $entity_type_id,
        '@plural_label' => $entity_type->getPluralLabel(),
      ];

      // Process each result function.
      foreach ($result_functions as $result_function_id => $result_function_definition) {
        // Extracting label from definition, unless it is for a specific field
        // then use the function ID so that you have context.
        $tokens['@result_function'] = (isset($result_function_definition['label']) && !strpos($result_function_id, '.')) ? $result_function_definition['label'] : $result_function_id;

        // Provide one to many relationship.
        $data[$data_table_name][$entity_type_id . '_vote_result_' . str_replace('.', '_', $result_function_id)] = [
          'title' => t('Vote Result "@result_function" for @plural_label', $tokens),
          'help' => t('This includes vote result "@result_function" for the @plural_label', $tokens),
          'relationship' => [
            'base' => 'votingapi_result',
            'base field' => 'entity_id',
            'field' => $id_key,
            'id' => 'standard',
            'label' => t('Vote Result "@result_function" for @plural_label', $tokens),
            'extra' => [
              ['field' => 'entity_type', 'value' => $entity_type_id],
              ['field' => 'function', 'value' => $result_function_id],
            ],
          ],
        ];

        // Flattened relationship for each vote type.
        foreach ($vote_types as $vote_type_name => $vote_type) {
          $tokens['@vote_type_label'] = $vote_type->label();

          $data[$data_table_name][$entity_type_id . '_vote_result_' . str_replace('.', '_', $result_function_id) . '_' . $vote_type_name] = [
            'title' => t('Vote Result "@result_function" for @plural_label: @vote_type_label', $tokens),
            'help' => t('This includes vote result "@result_function" for the @plural_label voted with @vote_type', $tokens),
            'relationship' => [
              'base' => 'votingapi_result',
              'base field' => 'entity_id',
              'field' => $id_key,
              'id' => 'standard',
              'label' => t('Vote Result "@result_function" for @plural_label: @vote_type_label', $tokens),
              'extra' => [
                ['field' => 'entity_type', 'value' => $entity_type_id],
                ['field' => 'function', 'value' => $result_function_id],
                ['field' => 'type', 'value' => $vote_type_name],
              ],
            ],
          ];
        }
      }
    }
  }
}

<?php

/**
 * @file
 * Provide views data.
 */

declare(strict_types = 1);

/**
 * Implements hook_views_data_alter().
 *
 * Add relationships to enable joining in entity import status on any content
 * entity views.
 */
function entity_share_client_views_data_alter(array &$data): void {
  $entity_type_definitions = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_type_definitions as $entity_type_id => $entity_type) {
    $data_table = $entity_type->getDataTable();
    if ($data_table) {
      $t_args = [
        '@type' => $entity_type->getLabel(),
      ];

      $data[$data_table]['entity_share_client_import_status_' . $entity_type_id] = [
        'title' => t('Entity import status for the @type entity type', $t_args),
        'help' => t('Add a relationship to fetch data on the import status of @type entities.', $t_args),
        'relationship' => [
          'group' => t('Entity Share'),
          'label' => t('Entity Import Status: @type', $t_args),
          'base' => 'entity_import_status',
          'base field' => 'entity_id',
          'field' => $entity_type->getKey('id'),
          'id' => 'standard',
          'extra' => [
            0 => [
              'field' => 'entity_type_id',
              'value' => $entity_type_id,
              'operator' => '=',
            ],
          ],
        ],
      ];
    }
  }
}

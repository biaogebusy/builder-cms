<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

use Drupal\entity_share_client\Views\ViewsFilterOptions;
use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the entity_import_status entity type.
 */
class EntityImportStatusViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Add a non-database field for the entity label.
    $data['entity_import_status']['entity_label'] = [
      'title' => $this->t('Imported entity label'),
      'field' => [
        'id' => 'entity_share_client_entity_label',
        'real field' => 'entity_id',
      ],
    ];

    // Add our custom field handlers for various fields.
    $data['entity_import_status']['remote_website']['field'] = [
      'id' => 'entity_share_client_config_entity_label',
      'entity_type_id' => 'remote',
    ];

    $data['entity_import_status']['channel_id']['field'] = [
      'id' => 'entity_share_client_channel',
    ];

    $data['entity_import_status']['entity_type_id']['field'] = [
      'id' => 'entity_share_client_entity_type_id',
    ];

    $data['entity_import_status']['entity_bundle']['field'] = [
      'id' => 'entity_share_client_entity_bundle',
    ];

    $data['entity_import_status']['entity_uuid']['field'] = [
      'id' => 'entity_share_client_uuid',
    ];

    $data['entity_import_status']['policy']['field'] = [
      'id' => 'entity_share_client_policy',
    ];

    // Add handlers and callbacks for various filters.
    $data['entity_import_status']['remote_website']['filter'] = [
      'id' => 'in_operator',
      'options callback' => ViewsFilterOptions::class . '::filterOptionsRemoteWebsite',
    ];

    $data['entity_import_status']['channel_id']['filter'] = [
      'id' => 'in_operator',
      'options callback' => ViewsFilterOptions::class . '::filterOptionsChannel',
    ];

    $data['entity_import_status']['entity_type_id']['filter'] = [
      'id' => 'in_operator',
      'options callback' => ViewsFilterOptions::class . '::filterOptionsEntityTypeId',
    ];

    $data['entity_import_status']['last_import']['filter'] = [
      'id' => 'date',
    ];

    $data['entity_import_status']['entity_bundle']['filter'] = [
      'id' => 'in_operator',
      'options callback' => ViewsFilterOptions::class . '::filterOptionsBundle',
    ];

    $data['entity_import_status']['policy']['filter'] = [
      'id' => 'in_operator',
      'options callback' => ViewsFilterOptions::class . '::filterOptionsPolicy',
    ];

    // In addition to the basic entity views data, we also add relationships
    // to enable joining in the data tables for all content entity types.
    $entityTypeDefinitions = $this->entityTypeManager->getDefinitions();
    foreach ($entityTypeDefinitions as $entityTypeId => $entityType) {
      $idKey = $entityType->getKey('id');
      $dataTable = $entityType->getDataTable();
      if ($idKey && $dataTable) {
        $t_args = [
          '@type' => $entityType->getLabel(),
        ];

        $data['entity_import_status']['entity_share_client_import_status_' . $entityTypeId]['relationship'] = [
          'group' => $this->t('Entity Share'),
          'help' => $this->t('Add a relationship to gain access to the fields of @type entities.', $t_args),
          'title' => $this->t('@type entity field data', $t_args),
          'label' => $this->t('@type entity field data', $t_args),
          'base' => $dataTable,
          'base field' => $idKey,
          'field' => 'entity_id',
          'id' => 'standard',
          'extra' => [
            0 => [
              'left_field' => 'entity_type_id',
              'value' => $entityTypeId,
              'operator' => '=',
            ],
          ],
        ];
      }
    }

    return $data;
  }

}

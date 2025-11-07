<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

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

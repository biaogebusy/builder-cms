<?php

namespace Drupal\commerce;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines a schema handler that supports defining base field indexes.
 */
class CommerceContentEntityStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping) {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);

    $entity_type = $this->entityTypeManager->getDefinition($storage_definition->getTargetEntityTypeId());
    $field_indexes = $entity_type->get('field_indexes');
    foreach ($field_indexes as $field_name) {
      if ($field_name == $storage_definition->getName()) {
        $this->addSharedTableFieldIndex($storage_definition, $schema);
      }
    }

    return $schema;
  }

}

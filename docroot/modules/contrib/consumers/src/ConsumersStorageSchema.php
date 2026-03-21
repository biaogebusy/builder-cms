<?php

namespace Drupal\consumers;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the consumer schema handler.
 */
class ConsumersStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
    $field_name = $storage_definition->getName();

    if ($table_name === $this->storage->getDataTable() && $field_name === 'client_id') {
      $this->addSharedTableFieldIndex($storage_definition, $schema);
    }

    return $schema;
  }

}

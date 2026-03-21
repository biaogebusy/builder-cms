<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the oauth2_token schema handler.
 */
class Oauth2TokenStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   *
   * Remove this method when the fix lands in core:
   * https://www.drupal.org/project/drupal/issues/3005447
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original): void {
    parent::onEntityTypeUpdate($entity_type, $original);

    $entity_schema = $this->getEntitySchema($entity_type, TRUE);
    $schema_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $schema_indexes = $entity_schema[$schema_table]['indexes'];
    foreach ($schema_indexes as $index_name => $index_fields) {
      if (!$this->database->schema()->indexExists($schema_table, $index_name)) {
        $this->database->schema()->addIndex($schema_table, $index_name, $index_fields, $entity_schema[$schema_table]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array {
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

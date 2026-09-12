<?php

namespace Drupal\commerce_payment;

use Drupal\commerce\CommerceContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Marks the "expires" field as unsigned.
 *
 * The "expires" field is a "timestamp" field which doesn't support setting the
 * "unsigned" boolean from the field definition. As a workaround, we do this
 * from the "storage_schema" handler.
 */
class PaymentMethodStorageSchema extends CommerceContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);

    if ($storage_definition->getName() === 'expires') {
      $schema['fields']['expires']['unsigned'] = TRUE;
    }

    return $schema;
  }

}

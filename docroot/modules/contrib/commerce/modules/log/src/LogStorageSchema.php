<?php

namespace Drupal\commerce_log;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the log schema handler.
 */
class LogStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $schema[$this->storage->getBaseTable()]['indexes'] += [
      'source_entity' => [
        'source_entity_id',
        'source_entity_type',
      ],
      'created' => ['created'],
      'category_id' => ['category_id'],
      'template_id' => ['template_id'],
    ];
    return $schema;
  }

}

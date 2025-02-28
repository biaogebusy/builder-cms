<?php

namespace Drupal\profile;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the profile schema handler.
 */
class ProfileStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($base_table = $this->storage->getBaseTable()) {
      $schema[$base_table]['indexes'] += [
        'profile__uid_type_status_is_default' => [
          'uid',
          'type',
          'status',
          'is_default',
        ],
      ];
    }

    return $schema;
  }

}

<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_notifications\Entity;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the message schema handler.
 */
class MessageStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($data_table = $this->storage->getBaseTable()) {
      unset($schema[$data_table]['indexes']['ckeditor5_message_field__uid__target_id']);

      $schema[$data_table]['fields']['created']['not null'] = TRUE;
      $schema[$data_table]['fields']['updated']['not null'] = TRUE;
      $schema[$data_table]['fields']['sent']['not null'] = TRUE;

      $schema[$data_table]['indexes'] += [
        'message__sent' => ['sent', 'updated'],
        'message__user2' => ['uid', 'entity_id', 'entity_type', 'sent'],
      ];
    }

    return $schema;
  }

}

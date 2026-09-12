<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\ckeditor5_premium_features\Entity\CollaborationStorageSchema;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Defines the message schema handler.
 */
class SuggestionStorageSchema extends CollaborationStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($data_table = $this->storage->getBaseTable()) {
      $schema[$data_table]['indexes'] += [
        'suggestion__chain' => ['chain_id'],
      ];
    }

    return $schema;
  }

}

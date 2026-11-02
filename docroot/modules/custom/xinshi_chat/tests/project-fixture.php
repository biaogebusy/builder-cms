<?php

/**
 * @file
 * Optional project structure for disposable Chat fixtures only.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

NodeType::create(['type' => 'project', 'name' => 'Project', 'new_revision' => FALSE])->save();
FieldStorageConfig::create(['field_name' => 'field_conversations', 'entity_type' => 'node',
  'type' => 'entity_reference', 'cardinality' => -1, 'settings' => ['target_type' => 'node']])->save();
FieldConfig::create(['field_name' => 'field_conversations', 'entity_type' => 'node',
  'bundle' => 'project', 'label' => 'Conversations', 'settings' => [
    'handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['conversation' => 'conversation']],
  ]])->save();

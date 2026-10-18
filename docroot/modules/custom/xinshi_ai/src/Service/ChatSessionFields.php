<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/** Defines the dormant split fields; online writers must be upgraded before use. */
final class ChatSessionFields {

  /** Return only new storage fields, without replacing existing message fields. */
  public static function definitions(int $version = 2): array {
    if (!in_array($version, [2, 3], TRUE)) {
      throw new \InvalidArgumentException('chat_session_unsupported_version');
    }
    $definitions = [
      'storage_version' => ['type' => 'integer', 'settings' => ['size' => 'normal']],
      'edit_version' => ['type' => 'integer', 'settings' => ['size' => 'big']],
      'storage_mode' => ['type' => 'string', 'settings' => ['max_length' => 32]],
      'content_format' => ['type' => 'string', 'settings' => ['max_length' => 255]],
      'run_id' => ['type' => 'string', 'settings' => ['max_length' => 36]],
      'run_created_at_ms' => ['type' => 'integer', 'settings' => ['size' => 'big']],
      'is_error' => ['type' => 'boolean', 'settings' => []],
      'output_completion' => ['type' => 'string', 'settings' => ['max_length' => 16]],
      'ui_review' => ['type' => 'string_long', 'settings' => []],
      'approval_timeline' => ['type' => 'string_long', 'settings' => []],
      'knowledge_refs' => ['type' => 'string', 'settings' => ['max_length' => 36],
        'cardinality' => 1024],
      'result_reference' => ['type' => 'string_long', 'settings' => []],
    ];
    if ($version === 3) {
      $definitions['plan_context'] = ['type' => 'string_long', 'settings' => []];
    }
    return $definitions;
  }

  /**
   * Install empty fields after checking all conflicts, without touching content.
   *
   * Only the explicit offline migration entry invokes this helper for an existing
   * site. Automatic installation/update hooks never convert or claim old data.
   */
  public static function install(int $version = 2): void {
    if (!NodeType::load('ai_session')) {
      throw new \RuntimeException('chat_session_bundle_missing');
    }
    $definitions = self::definitions($version);
    foreach ($definitions as $name => $definition) {
      $storage = FieldStorageConfig::loadByName('node', $name);
      if ($storage && ($storage->getType() !== $definition['type'] ||
        $storage->getCardinality() !== ($definition['cardinality'] ?? 1) ||
        !$storage->isRevisionable() || $storage->isTranslatable() ||
        array_diff(array_values($storage->getBundles()), ['ai_session']) !== [])) {
        throw new \RuntimeException('chat_session_field_conflict');
      }
      foreach ($definition['settings'] as $key => $value) {
        if ($storage && $storage->getSetting($key) !== $value) {
          throw new \RuntimeException('chat_session_field_conflict');
        }
      }
      $field = FieldConfig::loadByName('node', 'ai_session', $name);
      if ($field && ($field->isRequired() || $field->isTranslatable() ||
        $field->getDefaultValueLiteral() !== [] || $field->getDefaultValueCallback() !== '')) {
        throw new \RuntimeException('chat_session_field_conflict');
      }
    }
    foreach ($definitions as $name => $definition) {
      if (!FieldStorageConfig::loadByName('node', $name)) {
        FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name,
          'type' => $definition['type'], 'settings' => $definition['settings'],
          'cardinality' => $definition['cardinality'] ?? 1,
          'translatable' => FALSE])->save();
      }
      if (!FieldConfig::loadByName('node', 'ai_session', $name)) {
        FieldConfig::create(['entity_type' => 'node', 'bundle' => 'ai_session',
          'field_name' => $name, 'label' => $name, 'required' => FALSE,
          'translatable' => FALSE, 'default_value' => []])->save();
      }
    }
  }

}

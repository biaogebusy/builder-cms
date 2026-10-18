<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Schema;

/** Versioned structure contracts; old versions remain fixed for update hooks. */
final class Definition {

  public const CURRENT = 1;
  public const STORAGE_VERSION = 3;
  public const BUNDLES = ['conversation', 'ai_session'];

  /** Return module-managed properties, excluding site labels and presentation. */
  public static function fields(int $version = self::CURRENT): array {
    if ($version !== 1) {
      throw new \InvalidArgumentException('Unsupported Chat structure version.');
    }
    $string = ['type' => 'string', 'settings' => ['max_length' => 255]];
    $fields = [
      'conversation' => [
        'ai_optimized' => ['type' => 'boolean'],
        'chat_type' => $string,
        'max_tokens' => ['type' => 'integer', 'settings' => ['size' => 'normal']],
        'mode' => $string,
        'model' => $string + ['required' => TRUE],
        'platform' => $string + ['required' => TRUE],
        'sessions' => ['type' => 'entity_reference', 'cardinality' => -1,
          'settings' => ['target_type' => 'node'],
          'field_settings' => ['handler' => 'default:node',
            'handler_settings' => ['target_bundles' => ['ai_session' => 'ai_session']]],
          'exclusive' => TRUE],
        'temperature' => ['type' => 'float'],
        'top_p' => ['type' => 'float'],
      ],
      'ai_session' => [
        'content' => ['type' => 'text_long', 'exclusive' => TRUE],
        'reasoning_content' => ['type' => 'string_long', 'exclusive' => TRUE],
        'session_role' => $string + ['required' => TRUE],
        'img' => $string,
        'style_preset' => $string,
        'summary' => ['type' => 'string', 'settings' => ['max_length' => 2500], 'exclusive' => TRUE],
        'total_tokens' => ['type' => 'integer', 'settings' => ['size' => 'normal'], 'exclusive' => TRUE],
        'uiparsing' => ['type' => 'boolean'],
      ],
    ];
    // These are the current protocol fields. The offline A/B converter keeps its frozen contract.
    $split = [
      'storage_version' => ['type' => 'integer', 'settings' => ['size' => 'normal']],
      'edit_version' => ['type' => 'integer', 'settings' => ['size' => 'big']],
      'storage_mode' => ['type' => 'string', 'settings' => ['max_length' => 32]],
      'content_format' => $string,
      'run_id' => ['type' => 'string', 'settings' => ['max_length' => 36]],
      'run_created_at_ms' => ['type' => 'integer', 'settings' => ['size' => 'big']],
      'is_error' => ['type' => 'boolean'],
      'output_completion' => ['type' => 'string', 'settings' => ['max_length' => 16]],
      'ui_review' => ['type' => 'string_long'],
      'approval_timeline' => ['type' => 'string_long'],
      'knowledge_refs' => ['type' => 'string', 'settings' => ['max_length' => 36], 'cardinality' => 1024],
      'result_reference' => ['type' => 'string_long'],
      'plan_context' => ['type' => 'string_long'],
    ];
    foreach ($split as $name => $field) {
      $fields['ai_session'][$name] = $field + ['exclusive' => TRUE,
        'storage_translatable' => FALSE, 'default_value' => [], 'default_value_callback' => ''];
    }
    foreach ($fields as &$bundle) {
      foreach ($bundle as &$field) {
        $field += ['settings' => [], 'field_settings' => [], 'cardinality' => 1,
          'required' => FALSE, 'translatable' => FALSE, 'exclusive' => FALSE];
      }
    }
    return $fields;
  }

}

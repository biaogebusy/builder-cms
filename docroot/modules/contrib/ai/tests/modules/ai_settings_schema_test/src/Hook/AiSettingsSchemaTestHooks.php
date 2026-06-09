<?php

declare(strict_types=1);

namespace Drupal\ai_settings_schema_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Hook handlers for the AI settings schema test module.
 */
final class AiSettingsSchemaTestHooks {

  /**
   * Implements hook_ai_operation_types_alter().
   */
  #[Hook('ai_operation_types_alter')]
  public function operationTypesAlter(array &$operation_types): void {
    $operation_types['text_to_code'] = [
      'id' => 'text_to_code',
      'label' => new TranslatableMarkup('Text to Code'),
      'actual_type' => 'text_to_code',
      'filter' => [],
    ];
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    $definitions['ai.settings']['mapping']['default_providers']['mapping']['text_to_code'] = [
      'label' => 'Default text to code provider',
      'type' => 'ai_settings_schema_test.default_operation_provider',
    ];
  }

}

<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\TextToJsonField;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an json_native_binary field.
 */
#[AiAutomatorType(
  id: 'llm_json_native_binary_field',
  label: new TranslatableMarkup('LLM: JSON Field'),
  field_rule: 'json_native_binary',
  target: '',
)]
class LlmJsonNativeBinary extends TextToJsonField implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: JSON Field';

}

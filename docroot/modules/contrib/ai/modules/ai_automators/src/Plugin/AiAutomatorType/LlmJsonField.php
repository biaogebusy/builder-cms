<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\TextToJsonField;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an json field.
 */
#[AiAutomatorType(
  id: 'llm_json_field',
  label: new TranslatableMarkup('LLM: JSON Field'),
  field_rule: 'json',
  target: '',
)]
class LlmJsonField extends TextToJsonField implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: JSON Field';

}

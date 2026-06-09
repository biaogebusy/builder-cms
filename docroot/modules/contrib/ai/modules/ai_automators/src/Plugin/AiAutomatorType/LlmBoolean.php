<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Boolean;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an boolean field.
 */
#[AiAutomatorType(
  id: 'llm_boolean',
  label: new TranslatableMarkup('LLM: Boolean'),
  field_rule: 'boolean',
  target: '',
)]
class LlmBoolean extends Boolean implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Boolean';

}

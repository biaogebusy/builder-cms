<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\NumericRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an float field.
 */
#[AiAutomatorType(
  id: 'llm_float',
  label: new TranslatableMarkup('LLM: Float'),
  field_rule: 'float',
  target: '',
)]
class LlmFloat extends NumericRule implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Float';

}

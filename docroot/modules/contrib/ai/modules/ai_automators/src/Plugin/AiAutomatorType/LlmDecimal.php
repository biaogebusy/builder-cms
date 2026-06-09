<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\NumericRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an decimal field.
 */
#[AiAutomatorType(
  id: 'llm_decimal',
  label: new TranslatableMarkup('LLM: Decimal'),
  field_rule: 'decimal',
  target: '',
)]
class LlmDecimal extends NumericRule implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Decimal';

}

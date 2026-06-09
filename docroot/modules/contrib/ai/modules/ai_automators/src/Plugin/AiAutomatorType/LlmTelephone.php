<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Telephone;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a telephone field.
 */
#[AiAutomatorType(
  id: 'llm_telephone',
  label: new TranslatableMarkup('LLM: Telephone'),
  field_rule: 'telephone',
  target: '',
)]
class LlmTelephone extends Telephone implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Telephone';

}

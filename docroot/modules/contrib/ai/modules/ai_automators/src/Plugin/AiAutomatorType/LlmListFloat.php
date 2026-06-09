<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Lists;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an list_float field.
 */
#[AiAutomatorType(
  id: 'llm_list_float',
  label: new TranslatableMarkup('LLM: List'),
  field_rule: 'list_float',
  target: '',
)]
class LlmListFloat extends Lists implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: List';

}

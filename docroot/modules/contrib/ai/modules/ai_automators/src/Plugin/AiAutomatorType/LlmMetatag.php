<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Metatag;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for the custom field.
 */
#[AiAutomatorType(
  id: 'llm_metatag',
  label: new TranslatableMarkup('LLM: Metatag'),
  field_rule: 'metatag',
  target: '',
)]
class LlmMetatag extends Metatag implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Metatag';

}

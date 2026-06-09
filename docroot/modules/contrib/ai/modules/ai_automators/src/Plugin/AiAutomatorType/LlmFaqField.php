<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\FaqField;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an faq field.
 */
#[AiAutomatorType(
  id: 'llm_faq_field',
  label: new TranslatableMarkup('LLM: FAQ Field'),
  field_rule: 'faqfield',
  target: '',
)]
class LlmFaqField extends FaqField implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: FAQ Field';

}

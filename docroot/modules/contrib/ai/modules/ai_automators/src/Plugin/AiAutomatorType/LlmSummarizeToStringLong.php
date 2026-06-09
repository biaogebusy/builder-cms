<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Summarize;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'summarize_to_string_long',
  label: new TranslatableMarkup('Summarize'),
  field_rule: 'string_long',
  target: '',
)]
class LlmSummarizeToStringLong extends Summarize implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Summarize';

}

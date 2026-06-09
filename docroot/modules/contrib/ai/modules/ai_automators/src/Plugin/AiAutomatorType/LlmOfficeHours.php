<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\OfficeHours;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an office_hours field.
 */
#[AiAutomatorType(
  id: 'llm_office_hours',
  label: new TranslatableMarkup('LLM: Office Hours'),
  field_rule: 'office_hours',
  target: '',
)]
class LlmOfficeHours extends OfficeHours implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Office Hours';

}

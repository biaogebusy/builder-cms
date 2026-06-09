<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Address;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an address field.
 */
#[AiAutomatorType(
  id: 'llm_address',
  label: new TranslatableMarkup('LLM: Address'),
  field_rule: 'address',
  target: '',
)]
class LlmAddress extends Address implements AiAutomatorTypeInterface {
  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Address';

}

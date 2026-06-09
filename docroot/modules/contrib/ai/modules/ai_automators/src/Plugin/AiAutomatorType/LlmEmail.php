<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Email;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an email field.
 */
#[AiAutomatorType(
  id: 'llm_email',
  label: new TranslatableMarkup('LLM: Email'),
  field_rule: 'email',
  target: '',
)]
class LlmEmail extends Email implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Email';

}

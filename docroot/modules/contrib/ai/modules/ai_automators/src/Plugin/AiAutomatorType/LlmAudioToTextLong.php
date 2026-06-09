<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\AudioToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorType(
  id: 'llm_audio_to_text_long',
  label: new TranslatableMarkup('LLM: Audio to Text'),
  field_rule: 'text_long',
  target: '',
)]
class LlmAudioToTextLong extends AudioToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Audio to Text';

}

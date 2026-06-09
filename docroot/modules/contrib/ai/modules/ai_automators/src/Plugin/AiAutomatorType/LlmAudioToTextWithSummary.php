<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\AudioToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a text_with_summary field.
 */
#[AiAutomatorType(
  id: 'llm_audio_to_text_with_summary',
  label: new TranslatableMarkup('LLM: Audio to Text'),
  field_rule: 'text_with_summary',
  target: '',
)]
class LlmAudioToTextWithSummary extends AudioToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Audio to Text';

}

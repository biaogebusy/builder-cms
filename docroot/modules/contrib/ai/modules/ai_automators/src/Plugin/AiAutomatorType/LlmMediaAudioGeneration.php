<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\TextToMediaSpeech;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'llm_media_Audio_generation',
  label: new TranslatableMarkup('LLM: Media Audio Generation'),
  field_rule: 'entity_reference',
  target: 'media',
)]
class LlmMediaAudioGeneration extends TextToMediaSpeech implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Media Audio Generation';

}

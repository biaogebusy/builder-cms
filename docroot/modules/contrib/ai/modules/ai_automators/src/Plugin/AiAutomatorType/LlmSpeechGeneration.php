<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\TextToSpeech;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an audio field.
 */
#[AiAutomatorType(
  id: 'llm_audio_generation',
  label: new TranslatableMarkup('LLM: Audio Generation'),
  field_rule: 'file',
  target: 'file',
)]
class LlmSpeechGeneration extends TextToSpeech implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Audio Generation';

}

<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\TextToMediaImage;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'llm_media_image_generation',
  label: new TranslatableMarkup('LLM: Media Image Generation'),
  field_rule: 'entity_reference',
  target: 'media',
)]
class LlmMediaImageGeneration extends TextToMediaImage implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Media Image Generation';

}

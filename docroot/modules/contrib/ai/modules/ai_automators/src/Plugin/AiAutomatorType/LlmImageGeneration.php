<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\TextToImage;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an image field.
 */
#[AiAutomatorType(
  id: 'llm_image_generation',
  label: new TranslatableMarkup('LLM: Image Generation'),
  field_rule: 'image',
  target: 'file',
)]
class LlmImageGeneration extends TextToImage implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Image Generation';

}

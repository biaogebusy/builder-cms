<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\ImageAltText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an image alt text field.
 */
#[AiAutomatorType(
  id: 'llm_image_alt_text',
  label: new TranslatableMarkup('LLM: Image Alt Text'),
  field_rule: 'image',
  target: 'file',
)]
class LlmImageAltText extends ImageAltText implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Image Alt Text';

}

<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\VideoToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'llm_video_to_string_long',
  label: new TranslatableMarkup('LLM: Video to Text'),
  field_rule: 'string_long',
  target: '',
)]
class LlmVideoToStringLong extends VideoToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Video to Text';

}

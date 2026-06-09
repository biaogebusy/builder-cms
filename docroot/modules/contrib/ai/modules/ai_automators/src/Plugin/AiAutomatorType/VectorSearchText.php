<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\SearchToText;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * Vector search-based text extraction automator.
 */
#[AiAutomatorType(
  id: 'vector_search_text_long',
  label: new TranslatableMarkup('Vector Search: Text'),
  field_rule: 'string_long',
  target: '',
)]
class VectorSearchText extends SearchToText implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Vector Search: Text';

}

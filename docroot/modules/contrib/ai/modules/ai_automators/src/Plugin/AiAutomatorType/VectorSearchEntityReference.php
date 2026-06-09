<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\SearchToReference;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * Vector search-based entity reference automator.
 */
#[AiAutomatorType(
  id: 'vector_search_entity_reference',
  label: new TranslatableMarkup('Vector Search: Entity Reference'),
  field_rule: 'entity_reference',
  target: 'any',
)]
class VectorSearchEntityReference extends SearchToReference implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Vector Search: Entity Reference';

}

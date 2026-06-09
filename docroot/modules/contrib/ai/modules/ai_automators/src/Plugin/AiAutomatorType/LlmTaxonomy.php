<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\Taxonomy;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a taxonomy_term field.
 */
#[AiAutomatorType(
  id: 'llm_taxonomy',
  label: new TranslatableMarkup('LLM: Taxonomy'),
  field_rule: 'entity_reference',
  target: 'taxonomy_term',
)]
class LlmTaxonomy extends Taxonomy implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Taxonomy';

}

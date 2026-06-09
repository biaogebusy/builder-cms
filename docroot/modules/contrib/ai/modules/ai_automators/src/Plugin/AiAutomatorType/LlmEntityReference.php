<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\EntityReference;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an entity reference field.
 */
#[AiAutomatorType(
  id: 'llm_entity_reference',
  label: new TranslatableMarkup('LLM: Entity Reference'),
  field_rule: 'entity_reference',
  target: 'any',
)]
class LlmEntityReference extends EntityReference implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Entity Reference';

}

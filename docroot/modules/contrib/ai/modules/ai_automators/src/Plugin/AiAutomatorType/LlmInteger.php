<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\NumericRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for an integer field.
 */
#[AiAutomatorType(
  id: 'llm_integer',
  label: new TranslatableMarkup('LLM: Integer'),
  field_rule: 'integer',
  target: '',
)]
class LlmInteger extends NumericRule implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Integer';

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Since we allow any type of number we round it.
    $values = array_map(fn ($value) => round($value, 0), $values);
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}

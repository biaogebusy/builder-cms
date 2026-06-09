<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * The rules for a text_create_summary field.
 */
#[AiAutomatorType(
  id: 'llm_text_create_summary',
  label: new TranslatableMarkup('LLM: Text Summary'),
  field_rule: 'text_with_summary',
  target: '',
)]
class LlmTextCreateSummary extends ComplexTextChat implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty(array $value, array $automatorConfig = []) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Text Summary';

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $newValues = $entity->get($fieldDefinition->getName())->getValue();
    foreach ($values as $key => $value) {
      if (isset($newValues[$key]['value'])) {
        $newValues[$key]['summary'] = $value;
      }
    }
    $entity->set($fieldDefinition->getName(), $newValues);
  }

}

<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai\OperationType\Summarization\SummarizationInput;

/**
 * This is a base class for summarization.
 */
class Summarize extends RuleBase {

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'summarize';

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a base class for none-LLM summarizations.";
  }

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $values = [];
    $instance = $this->prepareLlmInstance('summarize', $automatorConfig);

    // Go through the field items and summarize them one by one.
    foreach ($entity->get($automatorConfig['base_field']) as $value) {
      $input = new SummarizationInput(
        text: $value->value,
      );
      $response = $instance->summarize($input, $automatorConfig['ai_model'], ['ai_automator_summarize']);
      $values[] = $response->getNormalized();
    }

    return $values;
  }

}

<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * This is a base class that can be used for LLMs simple boolean rules.
 */
class Boolean extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to boolean model.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text answer with a {{ true }} if there is some information about Pippi Longstockings. Otherwise answer {{ false }}.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, array $automatorConfig = []) {
    return empty($value[0]['value']) ? [] : $value;
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    $tokens = parent::tokens($entity);
    $tokens['true'] = 'The true value.';
    $tokens['false'] = 'The false value.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig);
    $tokens['true'] = 'TRUE';
    $tokens['false'] = 'FALSE';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"TRUE or FALSE\"}\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);

    foreach ($prompts as $prompt) {
      // Create new messages.
      $input = new ChatInput([
        new ChatMessage("user", $prompt),
      ]);
      $input = $this->applyGuardrailsToInput($input, $automatorConfig);

      $response = $instance->chat($input, $automatorConfig['ai_model'])->getNormalized();
      $this->assertNotStoppedByGuardrail($input);

      // Normalize the response.
      $values = json_decode(str_replace("\n", "", trim(str_replace(['```json', '```'], '', $response->getText()))), TRUE);

      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Has to be string boolean.
    if (!in_array($value['value'], ['TRUE', 'FALSE', '0', '1', 0, 1])) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Transform string to boolean.
    foreach ($values as $key => $value) {
      $values[$key] = in_array($value['value'], ['TRUE', '1', 1]) ? TRUE : FALSE;
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}

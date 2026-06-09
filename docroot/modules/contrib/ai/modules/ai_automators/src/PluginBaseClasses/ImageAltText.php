<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs alt text.
 */
class ImageAltText extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help writing out alt texts on images.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Look at the image and make a good alt text out of it.";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, array $automatorConfig = []) {
    // Check if the alt is empty in all values.
    foreach ($value as $item) {
      if (empty($item['alt'])) {
        return [];
      }
    }
    // Return the original value so baseShouldSave() can properly evaluate
    // $value[0] for the edit_mode check.
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"alt\": \"The alt text\"}}]. You should create one item for each image and give it back in the order it was provided.\n\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    // Has to have a alt text.
    if (empty($value['alt'])) {
      return FALSE;
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $items = [];
    foreach ($entity->get($fieldDefinition->getName()) as $delta => $item) {
      $items[$delta] = $item->getValue();
      // Preserve existing alt text unless edit_mode is enabled.
      if (empty($automatorConfig['edit_mode']) && !empty($items[$delta]['alt'])) {
        continue;
      }
      $items[$delta]['alt'] = $values[$delta]['alt'] ?? '';
    }
    $entity->set($fieldDefinition->getName(), $items);
  }

}

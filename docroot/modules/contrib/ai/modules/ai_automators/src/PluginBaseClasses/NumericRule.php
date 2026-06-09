<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple numeric rules.
 */
class NumericRule extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to decimal model.";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    $tokens = parent::tokens($entity);
    $tokens['min'] = 'A min numeric value, if set.';
    $tokens['max'] = 'A max numeric value, if set.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig, $delta);
    $tokens['min'] = $config['min'] ?? NULL;
    $tokens['max'] = $config['max'] ?? NULL;
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
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}]\n";
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
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    // Has to be number.
    if (!is_numeric($value)) {
      return FALSE;
    }
    // Has to be larger or equal to min.
    if (is_numeric($config['min']) && $config['min'] >= $value) {
      return FALSE;
    }
    // Has to be smaller or equal to max.
    if (is_numeric($config['max']) && $config['max'] <= $value) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

}

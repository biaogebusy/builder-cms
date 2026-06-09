<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * This is a base class that can be used for LLMs complex text chat.
 */
class ComplexTextChat extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    $this->getGeneralHelper()->addJoinerConfigurationFormField('automator', $form, $entity, $fieldDefinition, $defaultValues);
    return $form;
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

    // If we should join, we do that.
    if (isset($automatorConfig['joiner']) && $automatorConfig['joiner']) {
      $joiner = $automatorConfig['joiner'];
      if ($joiner == 'other') {
        $joiner = $automatorConfig['joiner_other'];
      }
      // Reset values.
      $total = [$this->getGeneralHelper()->joinValues($total, $joiner)];
    }
    return $total;
  }

}

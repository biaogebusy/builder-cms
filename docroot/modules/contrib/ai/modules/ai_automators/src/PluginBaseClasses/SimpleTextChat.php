<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * This is a base class that can be used for LLMs simple text chat/instructions.
 */
class SimpleTextChat extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to text model. It will give back the raw output.";
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    // Extract the code block types options.
    $codeBlockTypes = $this->getGeneralHelper()->getPromptCodeBlockExtractor()->codeBlockTypes;
    $options = [];
    foreach ($codeBlockTypes as $key => $codeBlockType) {
      $options[$key] = $codeBlockType['label'];
    }

    $form['automator_code_block_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Extract code block type'),
      '#options' => $options,
      '#default_value' => $defaultValues['automator_code_block_type'] ?? 'html',
      '#description' => $this->t('The type of code block to extract from the message if needed.'),
      '#empty_option' => $this->t('-- Save all --'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $value[] = $this->runRawChatMessage($prompt, $automatorConfig, $instance, $entity)->getText();
      if (!empty($value[0])) {
        $total = array_merge_recursive($total, $value);
      }
    }
    // If extraction is needed, we extract it.
    if (!empty($automatorConfig['code_block_type'])) {
      foreach ($total as $key => $value) {
        $total[$key] = $this->getGeneralHelper()->getPromptCodeBlockExtractor()->extract($value, $automatorConfig['code_block_type']);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    if (!empty($config['max_length'])) {
      $values = array_map(function ($value) use ($config) {
        return substr($value, 0, $config['max_length']);
      }, $values);
    }

    $entity->set($fieldDefinition->getName(), $values);
  }

}

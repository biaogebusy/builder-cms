<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * This is a base class that can be used for LLMs simple custom field rules.
 */
class CustomField extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help find complex amount of data and fill in complex field types with it.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context extract all quotes and fill in the quote, a translated quote into english, the persons name and the persons role.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, array $automatorConfig = []) {
    return isset($value[0]) && $value[0][key($value[0])] ? [1] : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    if (isset($config['field_settings'])) {
      foreach ($config['field_settings'] as $key => $value) {
        $form["automator_llm_custom_value_" . $key] = [
          '#type' => 'textarea',
          '#title' => $value['widget_settings']['label'] ?? $key,
          '#description' => $this->t('One sentence how the %label should be filled out. For instance "the original quote".', [
            '%label' => $value['widget_settings']['label'] ?? $key,
          ]),
          '#attributes' => [
            'rows' => 2,
          ],
          '#default_value' => $defaultValues["automator_llm_custom_value_" . $key] ?? '',
          '#weight' => 14,
          // This property will land into core soon, see
          // https://www.drupal.org/project/drupal/issues/3202631. It can stay
          // after this is added to Drupal core.
          '#normalize_newlines' => TRUE,
          // Until that the custom value callback is needed. Should be removed
          // after the issue mentioned above is merged into core and the minimum
          // supported Drupal version includes `#normalize_newlines` property.
          '#value_callback' => [Textarea::class, 'valueCallback'],
        ];

        $form["automator_llm_custom_oneshot_" . $key] = [
          '#type' => 'textarea',
          '#title' => $this->t('Example %label', [
            '%label' => $value['widget_settings']['label'] ?? $key,
          ]),
          '#description' => $this->t('One example %label of a filled out value for one shot learning. For instance "To be or not to be".', [
            '%label' => $value['widget_settings']['label'] ?? $key,
          ]),
          '#attributes' => [
            'rows' => 2,
          ],
          '#default_value' => $defaultValues["automator_llm_custom_oneshot_" . $key] ?? '',
          '#weight' => 14,
          // This property will land into core soon, see
          // https://www.drupal.org/project/drupal/issues/3202631. It can stay
          // after this is added to Drupal core.
          '#normalize_newlines' => TRUE,
          // Until that the custom value callback is needed. Should be removed
          // after the issue mentioned above is merged into core and the minimum
          // supported Drupal version includes `#normalize_newlines` property.
          '#value_callback' => [Textarea::class, 'valueCallback'],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    $example = [];
    $oneShot = [];
    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_custom_value_')) {
        $example[substr($key, strlen('llm_custom_value_'))] = $value;
      }
      elseif (str_starts_with($key, 'llm_custom_oneshot_')) {
        $oneShot[substr($key, strlen('llm_custom_oneshot_'))] = $value;
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\":" . json_encode($example) . "}]";
      $prompt .= "\n\nExample of one row:\n[{\"value\":" . json_encode($oneShot) . "}]\n";
      $prompts[$key] = $prompt;
    }

    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      // Create new messages.
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
    // Should be array, otherwise no validation for now.
    if (!is_array($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

}

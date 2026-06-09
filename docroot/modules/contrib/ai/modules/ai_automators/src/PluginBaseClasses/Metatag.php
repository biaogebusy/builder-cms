<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\ai\Utility\Textarea;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for LLMs metatag rules.
 */
class Metatag extends RuleBase {

  /**
   * The metatag manager.
   *
   * @var \Drupal\metatag\MetatagManager
   */
  protected $metaTagManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->metaTagManager = $container->get('metatag.manager');
    return $parent_instance;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can create personalized texts for different metatags based on context.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context create the different metatag fields according to the instructions for each.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, array $automatorConfig = []) {
    if (!isset($value[0]['value'])) {
      return [];
    }
    $values = Json::decode($value[0]['value']);
    $should_run = $value;
    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_tag_value') && $value) {
        $test = substr($key, strlen('llm_tag_value_'));
        if (!isset($values[$test]) || !$values[$test]) {
          $should_run = [];
        }
      }
    }
    return $should_run;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $groups = $this->metaTagManager->sortedGroups();
    $tags = $this->metaTagManager->sortedTags();
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    foreach ($groups as $group_key => $group) {
      $form['group'][$group_key] = [
        '#type' => 'details',
        '#title' => $this->t('Setup @group', ['@group' => $group['label']]),
        '#open' => FALSE,
        '#description' => $this->t('Write a subprompt for generating a text for the following tag description, you may reference the context from the main prompt. Keep empty to not generate anything.'),
      ];
      foreach ($tags as $tag_key => $tag) {
        if ($tag['group'] == $group_key) {
          $description = $tag['description'] ?? '';
          $form['group'][$group_key]["automator_llm_tag_value_$tag_key"] = [
            '#type' => 'textarea',
            '#title' => $this->t('Setup @group', ['@group' => $tag['label']]),
            '#description' => $this->t('Write a subprompt for this field, you may reference the context from the main prompt. Keep empty to not run the automators on this field. The description of the tag is: %description', ['%description' => $description]),
            '#default_value' => $defaultValues["automator_llm_tag_value_$tag_key"] ?? '',
            '#attributes' => [
              'rows' => 2,
            ],
            // This property will land into core soon, see
            // https://www.drupal.org/project/drupal/issues/3202631. It can stay
            // after this is added to Drupal core.
            '#normalize_newlines' => TRUE,
            // Until that the custom value callback is needed. Should be removed
            // after the issue mentioned above is merged into core and the
            // minimum supported Drupal version includes `#normalize_newlines`
            // property.
            '#value_callback' => [Textarea::class, 'valueCallback'],
          ];

          $form['group'][$group_key]["automator_llm_tag_example_$tag_key"] = [
            '#type' => 'textarea',
            '#title' => $this->t('Example of @tag', ['@tag' => $tag['label']]),
            '#description' => $this->t('Write an example of the @tag filled out. This is for the AI to understand better how to produce it.', ['@tag' => $tag['label']]),
            '#default_value' => $defaultValues["automator_llm_tag_example_$tag_key"] ?? '',
            '#attributes' => [
              'rows' => 2,
            ],
            // This property will land into core soon, see
            // https://www.drupal.org/project/drupal/issues/3202631. It can stay
            // after this is added to Drupal core.
            '#normalize_newlines' => TRUE,
            // Until that the custom value callback is needed. Should be removed
            // after the issue mentioned above is merged into core and the
            // minimum supported Drupal version includes `#normalize_newlines`
            // property.
            '#value_callback' => [Textarea::class, 'valueCallback'],
          ];
        }
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
    $available_tags = [];
    $tags = [];
    $examples = [];
    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_tag_value')) {
        $available_tags[] = substr($key, strlen('llm_tag_value_'));
        if ($value) {
          $tags[substr($key, strlen('llm_tag_value_'))] = $value;
        }
      }
      if (str_starts_with($key, 'llm_tag_example') && $value) {
        $examples[substr($key, strlen('llm_tag_example_'))] = $value;
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\":" . json_encode($tags) . "}]";
      $prompt .= "\n\nThe list of available tags is: " . implode(', ', $available_tags) . ". Do not generate values for any other tags.\n";
      $prompt .= "\n\nExample of one row:\n[{\"value\":" . json_encode($examples) . "}]\n";
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

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Only one value can be set, and its stored as a JSON blob.
    $entity->set($fieldDefinition->getName(), Json::encode($values[0]));
  }

}

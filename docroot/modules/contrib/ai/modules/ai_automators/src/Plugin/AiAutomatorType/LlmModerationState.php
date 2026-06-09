<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\content_moderation\ModerationInformation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for a text field.
 */
#[AiAutomatorType(
  id: 'llm_moderation_state',
  label: new TranslatableMarkup('LLM: Moderation State'),
  field_rule: 'string',
  target: '',
)]
class LlmModerationState extends RuleBase implements AiAutomatorTypeInterface {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformation|null
   */
  protected ModerationInformation|NULL $moderationInformation;

  public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    ?ModerationInformation $moderationInformation = NULL,
  ) {
    $this->aiPluginManager = $pluginManager;
    $this->formHelper = $formHelper;
    $this->promptJsonDecoder = $promptJsonDecoder;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $moderation = NULL;
    if ($container->has('content_moderation.moderation_information')) {
      $moderation = $container->get('content_moderation.moderation_information');
    }
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $moderation,
    );
  }

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Moderation State';

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, $automatorConfig = []) {
    if (!empty($automatorConfig['trigger_states']) && in_array($value[0]['value'], $automatorConfig['trigger_states'])) {
      return [];
    }
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Only allow if the field id is moderation_state.
    return $fieldDefinition->getName() === 'moderation_state';
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    $tokens = [
      'context' => 'The cleaned text from the base field.',
      'raw_context' => 'The raw text from the base field. Can include HTML',
      'max_amount' => 'The max amount of entries to set. If unlimited this value will be empty.',
    ];
    $flags = $this->getFlags($entity);
    foreach ($flags as $key => $label) {
      $tokens[$key] = 'The ' . $label . ' state.';
    }
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $values = $entity->get($automatorConfig['base_field'])->getValue();
    $flags = $this->getFlags($entity);
    $tokens = [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
    foreach ($flags as $key => $label) {
      $tokens[$key] = $key;
    }
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    // Get the moderation states.
    $options = $this->getFlags($entity);

    $form['automator_trigger_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Trigger on these states'),
      '#description' => $this->t('Select the moderation states that should trigger this automator to run. Do not select all states, only the starting states.'),
      '#options' => $options,
      '#default_value' => $defaultValues['automator_trigger_states'] ?? [],
    ];

    $form['automator_use_simple_model'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use small model'),
      '#description' => $this->t('Since smaller models might not be able to produce correct JSON, this will use free text instead and look for the moderation state inside the output prompt.'),
      '#default_value' => $defaultValues['automator_use_simple_model'] ?? FALSE,
    ];

    $form['automator_trigger_lookup'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Lookup for these states'),
      '#description' => $this->t('Select the moderation states that you should look for in your lookup. This is required.'),
      '#options' => $options,
      '#default_value' => $defaultValues['automator_trigger_lookup'] ?? [],
    ];

    $textFields = $this->getGeneralHelper()->getFieldsOfType($entity, 'string_long');
    $form['automator_store_explanation'] = [
      '#type' => 'select',
      '#options' => $textFields,
      '#empty_option' => $this->t('--Do not store--'),
      '#title' => $this->t('Store explanation'),
      '#description' => $this->t('Store the explanation of the moderation state in any unformatted long text field. For simple models this will be the full output for advanced it will ask specifically for the reason.'),
      '#default_value' => $defaultValues['automator_store_explanation'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    // Make sure that if this was enabled that the lookup is set.
    $found = FALSE;
    foreach ($formState->getValue('automator_trigger_lookup') as $value) {
      if ($value) {
        $found = TRUE;
      }
    }
    if (!$found) {
      $formState->setErrorByName('automator_trigger_lookup', $this->t('You must select at least one lookup state.'));
    }

    $found = FALSE;
    foreach ($formState->getValue('automator_trigger_states') as $value) {
      if ($value) {
        $found = TRUE;
      }
    }
    if (!$found) {
      $formState->setErrorByName('automator_trigger_states', $this->t('You must select at least one trigger state.'));
    }

  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      if (empty($automatorConfig['use_simple_model'])) {
        if (!empty($automatorConfig['store_explanation'])) {
          $prompt .= "\n\nAlso provide a 1 to 4 sentence reason for choosing the moderation state. Do not include any explanations outside of the reasoning, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"state\": \"the state name\", \"reasoning\": \"the reasoning for your choice\"}}]\n";
        }
        else {
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"state\": \"the state name\"}}]\n";
        }
      }
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      if (empty($automatorConfig['use_simple_model'])) {
        $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);

        if (!empty($values)) {
          $total = array_merge_recursive($total, $values);
        }
      }
      else {
        $total[] = $this->runRawChatMessage($prompt, $automatorConfig, $instance)->getText();
      }
    }

    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    if (is_string($value) && !empty($value)) {
      return TRUE;
    }
    if (is_array($value) && $value['state']) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    foreach ($values as $value) {
      if ($automatorConfig['store_explanation']) {
        if ($automatorConfig['use_simple_model']) {
          $entity->set($automatorConfig['store_explanation'], $value);
        }
        elseif (isset($value['reasoning'])) {
          $entity->set($automatorConfig['store_explanation'], $value['reasoning']);
        }
      }

      $allowed = [];
      foreach ($automatorConfig['trigger_lookup'] as $state => $lookup) {
        if ($lookup) {
          $allowed[] = $state;
        }
      }

      // If its simple values.
      if ($automatorConfig['use_simple_model']) {
        // Look for the trigger words - full words.
        foreach ($automatorConfig['trigger_lookup'] as $state) {
          // Just do full words, not partials.
          $word = strtok($value, " \n\t");
          // Look to find a word.
          while ($word !== FALSE) {
            // No dots.
            if (str_replace('.', '', $word) == $state) {
              $entity->set($fieldDefinition->getName(), $state);
              break;
            }
            $word = strtok(" \n\t");
          }
        }
      }
      else {
        if (isset($value['state']) && in_array($value['state'], $allowed)) {
          $entity->set($fieldDefinition->getName(), $value['state']);
        }
      }
    }
  }

  /**
   * Get flags for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The flags.
   */
  protected function getFlags(ContentEntityInterface $entity) {
    $flags = [];
    if ($this->moderationInformation->isModeratedEntityType($entity->getEntityType())) {
      $workflow = $this->moderationInformation->getWorkflowForEntityTypeAndBundle($entity->getEntityTypeId(), $entity->bundle());
      $plugin = $workflow->getTypePlugin();
      $config = $plugin->getConfiguration();
      if (isset($config['states'])) {
        foreach ($config['states'] as $key => $data) {
          $flags[$key] = $data['label'];
        }
      }
    }
    return $flags;
  }

}

<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_automators\AiAutomatorEntityModifier;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * This is an abstract base class for automator actions.
 */
abstract class AutomatorBaseAction extends FieldWidgetActionBase {

  /**
   * If the values should be cleared from the entity.
   *
   * This is used to ensure that the field values are cleared before
   * running the automator, so that it can populate the field with new values
   * if its the base value.
   *
   * @var bool
   */
  protected bool $clearEntity = TRUE;

  /**
   * The form element property to use for the automator.
   *
   * @var string
   */
  public string $formElementProperty = 'value';

  /**
   * The automators plugin manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager
   */
  protected AiAutomatorTypeManager $automatorTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity modifier service.
   *
   * @var \Drupal\ai_automators\AiAutomatorEntityModifier
   */
  protected AiAutomatorEntityModifier $entityModifier;

  /**
   * The AI provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->automatorTypeManager = $container->get('plugin.manager.ai_automator');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityModifier = $container->get('ai_automator.entity_modifier');
    $instance->aiProvider = $container->get('ai.provider');
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'settings' => [
        'automator_id' => '',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $settings = $this->getConfiguration();
    if (!empty($settings['settings'])) {
      $settings = $settings['settings'];
    }
    $element = parent::buildConfigurationForm($form, $form_state, $action_id);
    $element['enabled']['#title'] = $this->t('Enable Automators');
    $element['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Enable an Automator'),
    ];
    $field_definition = $this->getFieldDefinition();
    if (!empty($field_definition)) {
      $element['settings']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][third_party_settings][field_widget_actions][' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
        ],
      ];
    }

    // Get the entity type and bundle from the field definition.
    $entity_type = $this->getFieldDefinition()->getTargetEntityTypeId();
    $bundle = $this->getFieldDefinition()->getTargetBundle();
    $field_name = $this->getFieldDefinition()->getName();

    $options = $this->getAutomatorsOptions($entity_type, $bundle, $field_name);

    $element['settings']['automator_id'] = [
      '#title' => $this->t('Automator to use for suggestions'),
      '#type' => 'select',
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Pick an automator -'),
      '#default_value' => $settings['automator_id'] ?? '',
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'aiAutomatorsAjax';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [
      'ai_automators/field_widget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    parent::actionButton($form, $form_state, $context);
    $fieldName = $context['items']->getFieldDefinition()->getName();
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($form['#delta'])) {
      $widgetId .= '_' . $form['#delta'];
    }
    $form[$widgetId]['#attributes']['class'][] = 'button--small';
    $form[$widgetId]['#attributes']['class'][] = 'btn-small';
    $form[$widgetId]['#attributes']['class'][] = 'button-automator-ai';
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // If the field definition is not set, its not the setup form.
    if (!$this->getFieldDefinition()) {
      return TRUE;
    }
    $entity_type = $this->getFieldDefinition()->getTargetEntityTypeId();
    $bundle = $this->getFieldDefinition()->getTargetBundle();
    $field_name = $this->getFieldDefinition()->getName();
    // Only show if an Automator is configured for the field widget.
    return count($this->getAutomatorsOptions($entity_type, $bundle, $field_name)) > 0;
  }

  /**
   * Helper function to check if automators are enabled for the field widget.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   An array of automators that are enabled for the field widget.
   */
  public function getAutomatorsOptions(string $entity_type, string $bundle, string $field_name): array {
    // Get all automator rules.
    $automators = $this->automatorTypeManager->getDefinitions();
    $automator_rules = [];
    foreach ($automators as $id => $definition) {
      $automator_rules[] = $id;
    }
    $options = [];
    // Load all automator configurations.
    /** @var \Drupal\ai_automators\Entity\AiAutomatorInterface[] $automator_configurations */
    $automator_configurations = $this->entityTypeManager->getStorage('ai_automator')->loadMultiple();
    foreach ($automator_configurations as $automator) {
      // Check so the entity type, bundle and rule match.
      $configured_entity_type = $automator->get('entity_type');
      $configured_bundle = $automator->get('bundle');
      $configured_rule = $automator->get('rule');
      $configured_field_name = $automator->get('field_name');
      if (
        in_array($configured_rule, $automator_rules) &&
        $configured_entity_type === $entity_type &&
        $configured_field_name === $field_name &&
        ($configured_bundle === $bundle || empty($configured_bundle))
      ) {
        $options[$automator->id()] = $automator->label();
      }
    }
    return $options;
  }

  /**
   * Function to populate values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_key
   *   The form key for the field.
   * @param int|null $key
   *   The key for the field item, used for multi-value fields.
   */
  public function populateAutomatorValues(array &$form, FormStateInterface $form_state, string $form_key, ?int $key = NULL): array {
    // Get the content entity from form object.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = static::buildEntity($form, $form_state);

    // Check if automator still exists.
    $automator_id = $this->getConfiguration()['settings']['automator_id'] ?? NULL;
    if ($automator_id) {
      $automator = $this->entityTypeManager->getStorage('ai_automator')->load($automator_id);
      if (!$automator) {
        // Log the issue and return gracefully instead of causing fatal error.
        $this->loggerFactory->get('ai_automators')->warning('Automator @automator_id not found for field widget action. The automator may have been deleted.', [
          '@automator_id' => $automator_id,
        ]);
        return $form[$form_key] ?? [];
      }
    }

    // Delete all values from the field, so you can recreate.
    if ($this->clearEntity) {
      $entity->get($form_key)->filterEmptyItems();
    }
    $form_state->setValue($form_key, NULL);
    // Run the automator for the entity.
    $entity = $this->entityModifier->saveEntity($entity, FALSE, $form_key, FALSE);
    // Ensure the widget has enough elements for all values.
    $form[$form_key]['widget']['#items_count'] = count($entity->get($form_key));

    return $this->saveFormValues($form, $form_key, $entity, $key);
  }

  /**
   * Function to save the form values.
   *
   * @param array $form
   *   The form array.
   * @param string $form_key
   *   The form key for the field.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param int|null $key
   *   The key for the field item, used for multi-value fields.
   *
   * @return array
   *   The updated form array with values populated.
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {

    if (is_null($key)) {
      // If not key is provided, we should iterate through all items.
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->get($this->formElementProperty)) {
          if ($item && $item->get($this->formElementProperty)) {
            $form[$form_key]['widget'][$index][$this->formElementProperty]['#value'] = $item->get($this->formElementProperty)->getValue();
          }
        }
      }
    }
    else {
      if (isset($entity->get($form_key)[$key])) {
        $item = NULL;
        foreach ($entity->get($form_key) as $index => $item) {
          if ($index === $key) {
            break;
          }
        }
        if ($item && $item->get($this->formElementProperty)) {
          $form[$form_key]['widget'][$key][$this->formElementProperty]['#value'] = $item->get($this->formElementProperty)->getValue();
        }
      }
    }

    return $form[$form_key];
  }

}

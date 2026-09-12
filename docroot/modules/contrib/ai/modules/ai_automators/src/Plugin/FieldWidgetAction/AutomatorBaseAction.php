<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_automators\AiAutomatorEntityModifier;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  protected string $formElementProperty = 'value';

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
    // Attach a dedicated submit handler so the automator runs once during
    // the submit phase. The AJAX callback then only returns the rebuilt
    // form slice (no second automator run). Plugins with non-standard
    // form layouts (e.g. ImageAltText on media_library_add_form) can keep
    // populating values from their AJAX callback; runAutomatorSubmit()
    // bails gracefully for those cases.
    $form[$widgetId]['#submit'][] = [$this, 'runAutomatorSubmit'];
    $form[$widgetId]['#executes_submit_callback'] = TRUE;
    $form[$widgetId]['#automator_config'] = $this->getConfiguration();
  }

  /**
   * Submit handler that runs the automator and rebuilds the form.
   *
   * Populates widget state and user input so the rebuilt form contains
   * enough delta slots to hold every value the automator returned.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function runAutomatorSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    // Defensive fallback: derive the field name from #array_parents if
    // the button doesn't carry the property (e.g. a plugin that
    // overrides actionButton() without calling parent).
    if ($field_name === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      $field_name = $array_parents[0] ?? NULL;
    }
    // Bail if the field is not a direct child of $form — e.g.
    // media_library_add_form nests fields under
    // $form['media'][$delta]['fields'], so those plugins override
    // aiAutomatorsAjax() and drive populateAutomatorValues() themselves.
    if (!$field_name || !isset($form[$field_name])) {
      return;
    }
    $key = $triggering_element['#field_widget_action_field_delta'] ?? NULL;
    $this->populateAutomatorValues($form, $form_state, $field_name, is_numeric($key) ? (int) $key : NULL);
  }

  /**
   * Default AJAX handler returning the rebuilt field widget.
   *
   * The real work happens in runAutomatorSubmit(). Plugins only need to
   * override this if they have a non-standard form layout (see
   * ImageAltText / ImageFilename for the media_library_add_form case).
   *
   * @param array $form
   *   The form array (already rebuilt when we get here).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The field widget subtree to render in the AJAX response.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    // Defensive fallback: derive the field name from #array_parents if
    // the button doesn't carry the property (e.g. a plugin that
    // overrides actionButton() without calling parent).
    if ($form_key === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      $form_key = $array_parents[0] ?? NULL;
    }
    if (!$form_key || !isset($form[$form_key])) {
      return [];
    }
    return $form[$form_key];
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
    /** @var \Drupal\ai_automators\AiAutomatorInterface[] $automator_configurations */
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
    $entity = $this->buildEntity($form, $form_state);

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
    // Run the specific automator for the entity. Passing the automator ID
    // ensures only this automator runs, even when multiple automators are
    // configured on the same field. Catch any failure so the user sees a
    // real error message instead of a silent no-op.
    try {
      $entity = $this->entityModifier->saveEntity($entity, FALSE, $form_key, FALSE, $automator_id);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_automators')->error('AI automator failed for field @field: @msg', [
        '@field' => $form_key,
        '@msg' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('The AI automator failed to run. Please try again or check the logs for details.'));
      return $form[$form_key] ?? [];
    }

    // saveEntity() returns NULL when no automator config matches this
    // field/automator combination (e.g. the automator has since been
    // reconfigured for a different field). Bail gracefully instead of
    // calling methods on NULL.
    if (!$entity) {
      $this->loggerFactory->get('ai_automators')->warning('Automator @automator_id did not process field @field. Check the automator configuration.', [
        '@automator_id' => $automator_id,
        '@field' => $form_key,
      ]);
      return $form[$form_key] ?? [];
    }

    if (!$entity->get($form_key)->isEmpty()) {
      $this->setFormInput($entity, $form_state, $form_key);
      $this->updateItemsCount($form, $form_state, $form_key, $entity->get($form_key)->count());
      $form_state->setRebuild();
    }
    // Still call saveFormValues() so existing downstream overrides keep
    // running. The return value is discarded by the AJAX flow.
    return $this->saveFormValues($form, $form_key, $entity, $key);
  }

  /**
   * Populates form values for the rebuilt widget (legacy hook).
   *
   * No-op in the default flow — setFormInput() writes user input during
   * the submit phase and the rebuilt form picks it up automatically. The
   * base class still invokes this method so existing downstream overrides
   * continue to run; the return value is discarded.
   *
   * New plugins should override setFormInput() / transformFormInput()
   * rather than this method.
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
    return $form[$form_key] ?? [];
  }

  /**
   * Transforms a field item into the shape expected as user input.
   *
   * Default implementation returns $item->toArray(). Plugins override this
   * when the widget's user input shape differs from the field's storage
   * shape (e.g. ImageAltText maps target_id → fids).
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   The field item.
   *
   * @return array
   *   The transformed array to be written to user input.
   */
  protected function transformFormInput(ComplexDataInterface $item): array {
    return $item->toArray();
  }

  /**
   * Writes automator results into $form_state->getUserInput().
   *
   * Default: writes one entry per delta under $input[$form_key][$index].
   * Plugins targeting widgets that declare multiple_values=TRUE (single
   * form element for all items) must override this method because each
   * such widget expects its own flat shape:
   *   - boolean_checkbox → $input[$form_key]['value']
   *   - options_select / chosen_select → $input[$form_key] = [id, id]
   *   - entity_reference_autocomplete_tags → $input[$form_key]['target_id']
   *
   * See widgetHandlesMultipleValues() for a helper to detect this, used
   * by ClassificationOptionsSelect which supports both cshs (per-delta)
   * and options_select / chosen_select (flat list) under one plugin.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity returned by the automator.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to mutate.
   * @param string $form_key
   *   The field name.
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $input = $form_state->getUserInput();
    foreach ($entity->get($form_key) as $index => $item) {
      $input[$form_key][$index] = $this->transformFormInput($item);
    }
    $form_state->setUserInput($input);
  }

  /**
   * Bumps the rebuilt widget's items_count so it scaffolds enough deltas.
   *
   * Multi-value widgets render N delta sub-elements based on field state's
   * items_count. When an automator produces more values than the widget
   * was originally rendered for, we must push the new count into field
   * state BEFORE the form rebuilds — otherwise the rebuild scaffolds only
   * the original slots and the extra values have nowhere to land.
   *
   * @param array $form
   *   The form array; only $form[$form_key]['widget']['#field_parents']
   *   is read.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state carrying the form display in its storage.
   * @param string $form_key
   *   The field name.
   * @param int $count
   *   The number of delta items the automator produced (field item count).
   */
  protected function updateItemsCount(array $form, FormStateInterface $form_state, string $form_key, int $count): void {
    $parents = $form[$form_key]['widget']['#field_parents'] ?? [];
    $form_display = NestedArray::getValue($form_state->getStorage(), ['form_display']);
    if (!$form_display) {
      return;
    }
    $renderer = $form_display->getRenderer($form_key);
    if (!$renderer) {
      return;
    }
    $field_state = $renderer->getWidgetState($parents, $form_key, $form_state);
    // formMultipleElements iterates 0..items_count so required is count-1.
    $required = max(0, $count - 1);
    if ($required > ($field_state['items_count'] ?? 0)) {
      $field_state['items_count'] = $required;
      $renderer->setWidgetState($parents, $form_key, $form_state, $field_state);
    }
  }

  /**
   * Whether the widget rendering $form_key declares multiple_values=TRUE.
   *
   * Multiple-values widgets render a single form element for all deltas,
   * and expect a flat user-input shape at $input[$form_key] — not the
   * per-delta shape the default setFormInput() writes.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state carrying the form display in its storage.
   * @param string $form_key
   *   The field name.
   *
   * @return bool
   *   TRUE if the widget handles all values as one element, FALSE otherwise
   *   (including when the form display / widget cannot be resolved).
   */
  protected function widgetHandlesMultipleValues(FormStateInterface $form_state, string $form_key): bool {
    $form_display = NestedArray::getValue($form_state->getStorage(), ['form_display']);
    $widget = $form_display?->getRenderer($form_key);
    return !empty($widget?->getPluginDefinition()['multiple_values']);
  }

}

<?php

namespace Drupal\field_widget_actions;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for FieldWidgetAction plugins.
 */
abstract class FieldWidgetActionBase extends PluginBase implements FieldWidgetActionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The target property of the form element.
   */
  const FORM_ELEMENT_PROPERTY = 'value';

  /**
   * The widget plugin instance.
   *
   * @var \Drupal\Core\Field\WidgetInterface|null
   */
  protected ?WidgetInterface $widget = NULL;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|null
   */
  protected ?FieldDefinitionInterface $fieldDefinition = NULL;

  /**
   * Constructs FieldWidgetActionBase instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => FALSE,
      'button_label' => $this->getLabel(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->getPluginDefinition()['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetTypes(): array {
    return $this->pluginDefinition['widget_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypes(): array {
    return $this->pluginDefinition['field_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = [];
    $configuration = $this->getConfiguration();
    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $configuration['enabled'] ?? FALSE,
    ];
    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $configuration['button_label'] ?? '',
    ];
    $element['plugin_id'] = [
      '#type' => 'value',
      '#value' => $this->getPluginId(),
    ];
    $element['weight'] = [
      '#type' => 'hidden',
      '#default_value' => $configuration['weight'] ?? 0,
      '#attributes' => [
        'class' => ['field-widget-action-element-order-weight'],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * Build the entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity this field is attached to or NULL.
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = NULL;
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ContentEntityFormInterface) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form_state->getFormObject()->buildEntity($form, $form_state);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget(): ?WidgetInterface {
    return $this->widget;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidget(?WidgetInterface $widget): void {
    $this->widget = $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface {
    return $this->fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldDefinition(?FieldDefinitionInterface $fieldDefinition): void {
    $this->fieldDefinition = $fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [];
  }

  /**
   * Gets the button label.
   *
   * @return string
   *   The button label.
   */
  public function getButtonLabel(): string {
    return $this->configuration['button_label'] ?: $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function completeFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    if ($this->getAjaxCallback()) {
      if (!empty($form['widget'][0]['#group'])) {
        $form['widget']['#process'][] = [$this, 'processWidgetWithGroup'];
      }
      else {
        // Add wrapper.
        $prefix = $form['#prefix'] ?? '';
        $suffix = $form['#suffix'] ?? '';
        $form['#prefix'] = '<div id="field-widget-action-' . $context['items']->getFieldDefinition()->getName() . '" class="field-widget-action-element-wrapper">' . $prefix;
        $form['#suffix'] = $suffix . '</div>';
        $form['#attributes']['class'][] = 'field-widget-action-element';
      }
    }
    $plugin_definition = $this->getPluginDefinition();
    if (empty($plugin_definition['multiple'])) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Process the element with #group property.
   *
   * @param array $element
   *   The given element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public function processWidgetWithGroup(array $element, FormStateInterface $form_state, array &$form) {
    if (!empty($element[0]['#group'])) {
      $group = $element[0]['#group'];
      // Add wrapper.
      $form[$group]['#prefix'] = '<div id="field-widget-action-' . $group . '" class="field-widget-action-element-wrapper">';
      $form[$group]['#suffix'] = '</div>';
      $form[$group]['#attributes']['class'][] = 'field-widget-action-element';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    $plugin_definition = $this->getPluginDefinition();
    if (!empty($plugin_definition['multiple'])) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Returns the action button widget ID.
   *
   * @param string $fieldName
   *   The field name.
   * @param array $context
   *   The context.
   *
   * @return string
   *   The Widget ID.
   */
  protected function getActionButtonWidgetId(string $fieldName, array $context): string {
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widgetId .= '_' . $context['delta'];
    }
    return $widgetId;
  }

  /**
   * Returns the action button depending on the `multiple` value of definition.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $context
   *   The context.
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    $fieldName = $context['items']->getFieldDefinition()->getName();
    $widgetId = $this->getActionButtonWidgetId($fieldName, $context);
    $weight = $this->configuration['weight'] ?? 0;
    $form[$widgetId] = [
      '#type' => 'button',
      '#value' => $this->getButtonLabel(),
      '#weight' => $weight + 10,
      '#name' => $widgetId,
      '#attributes' => [
        'class' => [
          'button--primary',
          'primary',
          'btn-primary',
          'field-widget-action-widget-button',
          'field-widget-action-' . $this->getPluginId(),
        ],
        'data-wrapper-id' => 'field-widget-action-' . $fieldName,
        'data-widget-id' => $this->getPluginId(),
        'data-widget-field' => $fieldName,
        'data-widget-delta' => $context['delta'] ?? '',
      ],
      '#field_widget_action_field_name' => $fieldName,
      // When called from hook_field_widget_complete_form, delta is not present.
      '#field_widget_action_field_delta' => $context['delta'] ?? NULL,
      '#field_widget_action_settings' => $this->getConfiguration(),
    ];
    $libraries = $this->getLibraries();
    if (!empty($libraries)) {
      $form[$widgetId]['#attached'] = [
        'library' => $libraries,
      ];
    }
    if ($this->getAjaxCallback()) {
      if (!empty($form['#group'])) {
        $fieldName = $form['#group'];
      }
      $form[$widgetId]['#ajax'] = [
        'callback' => [$this, $this->getAjaxCallback()],
        'wrapper' => 'field-widget-action-' . $fieldName,
        'prevent' => 'submit',
        'suppress_required_fields_validation' => TRUE,
      ];
    }
  }

  /**
   * Returns the target css selector for suggestions.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The css selector to fill in with the selected suggestion.
   */
  protected function getSuggestionsTarget(array &$form, FormStateInterface $form_state): string {
    $target_element = $this->getTargetElement($form, $form_state);
    if (!$target_element) {
      return '';
    }

    // Safely try data-drupal-selector, fallback to id, or return empty.
    return $target_element['#attributes']['data-drupal-selector']
      ?? $target_element['#id']
      ?? '';
  }

  /**
   * Returns the target form element this action is attached to.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  protected function getTargetElement(array &$form, FormStateInterface $form_state): array {
    // Get the triggering element, the button is inside the same field widget.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];

    // Remove the button key.
    array_pop($array_parents);

    // Identify the correct property name (default to 'value').
    $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    return NestedArray::getValue($form, $array_parents) ?? [];
  }

  /**
   * Gets the delta of form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int|null
   *   The delta of the form element or null if it is attached to the complete
   *   widget form.
   */
  protected function getTargetElementDelta(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return $triggering_element['#field_widget_action_field_delta'] ?? NULL;
  }

  /**
   * Gets the field name that corresponds to form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The field name for the form element.
   */
  protected function getTargetElementFieldName(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return $triggering_element['#field_widget_action_field_name'] ?? '';
  }

  /**
   * Returns suggestions in a dialog.
   *
   * @param array|string $suggestions
   *   The content to display in a dialog.
   * @param string $selector
   *   The selector for inserting a suggestion.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  protected function returnSuggestions(array|string $suggestions, $selector = '') {
    $message = '';
    // If it is empty string or empty array, no suggestions were actually
    // provided, so the dialog should not show anything selectable.
    if (!empty($suggestions)) {
      if (!is_array($suggestions)) {
        $suggestions = [$suggestions];
      }
      $message = [
        '#theme' => 'field_widget_actions_suggestions',
        '#suggestions' => $suggestions,
        '#attached' => [
          'library' => [
            'field_widget_actions/suggestions',
          ],
        ],
      ];
    }

    $response = new AjaxResponse();
    // Collect all messages emitted so far. In case of validation errors we need
    // to display them as well right away.
    foreach ($this->messenger->all() as $type => $items) {
      foreach ($items as $item) {
        $response->addCommand(new MessageCommand($item, NULL, ['type' => $type]));
      }
    }
    // Remove all messages, as they will be displayed with ajax commands.
    $this->messenger->deleteAll();
    if (!empty($selector)) {
      $response->addCommand(new SettingsCommand(['fwa_suggestion_target' => ['target' => $selector]], TRUE));
    }
    if (empty($message)) {
      $message = $this->t('Unfortunately no suggestions were provided.');
    }
    $response->addCommand(new OpenModalDialogCommand($this->t('Suggestions'), $message, [
      'width' => '80%',
      'dialogClass' => 'ui-dialog-fwa-suggestions',
    ]));
    return $response;
  }

}

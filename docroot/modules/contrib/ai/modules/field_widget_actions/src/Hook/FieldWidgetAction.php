<?php

namespace Drupal\field_widget_actions\Hook;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\FieldWidgetActionInterface;
use Drupal\field_widget_actions\FieldWidgetActionManagerInterface;
use Drupal\field_widget_actions\Plugin\ConfigAction\SetupFieldWidgetAction;

/**
 * Class for hooks from field_widget_actions module.
 */
class FieldWidgetAction {

  use StringTranslationTrait;

  /**
   * Constructs hook class.
   *
   * @param \Drupal\field_widget_actions\FieldWidgetActionManagerInterface $fieldWidgetActionManager
   *   The field widget actions manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid service.
   */
  public function __construct(
    protected FieldWidgetActionManagerInterface $fieldWidgetActionManager,
    protected UuidInterface $uuid,
  ) {

  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'field_widget_actions_suggestions' => [
        'variables' => [
          'suggestions' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_field_widget_third_party_settings_form().
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, array $form, FormStateInterface $form_state) {
    $element = [];
    $allowed_field_widget_actions = $this->fieldWidgetActionManager->getAllowedFieldWidgetActions($plugin->getPluginId(), $field_definition->getType());
    if (!empty($allowed_field_widget_actions)) {
      $wrapper_id = 'field-widget-actions-' . $field_definition->getName();
      $element = [
        '#type' => 'details',
        '#title' => $this->t('Field Widget Actions'),
        '#description' => $this->t('You can add buttons to field widget on the form that perform various actions.'),
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#attached' => [
          'library' => [
            'field_widget_actions/admin_ui',
          ],
        ],
        '#attributes' => [
          'class' => [
            'field-widget-actions-wrapper',
          ],
        ],
      ];
      $options = [];
      foreach ($allowed_field_widget_actions as $plugin_id => $allowed_field_widget_action) {
        if (empty($allowed_field_widget_action['category'])) {
          $allowed_field_widget_action['category'] = $this->t('Other');
        }
        $category = (string) $allowed_field_widget_action['category'];
        $options[$category][$plugin_id] = $allowed_field_widget_action['label'];
      }
      $element['new'] = [
        '#tree' => TRUE,
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'field-widget-actions-add-new',
          ],
        ],
        '#parents' => [],
      ];
      $element['new']['action'] = [
        '#type' => 'select',
        '#title' => $this->t('Add New Action'),
        '#options' => $options,
        '#empty_option' => $this->t('- None -'),
        '#wrapper_attributes' => [
          'class' => [
            'field-widget-actions-select',
          ],
        ],
      ];
      $element['new']['add'] = [
        '#type' => 'button',
        '#name' => $field_definition->getName() . '_add_field_widget_actions',
        '#value' => $this->t('Add action'),
        '#ajax' => [
          'callback' => [static::class, 'addAction'],
          'event' => 'click',
          'wrapper' => $wrapper_id,
        ],
        '#suffix' => '<p>' . $this->t('Sort the actions with drag and drop (titles of the blocks below are draggable with mouse)') . '</p>',
      ];
      $enabled_plugins = $plugin->getThirdPartySettings('field_widget_actions');
      $triggering_element = $form_state->getTriggeringElement();
      if (!empty($triggering_element)) {
        $parents = $triggering_element['#parents'];
        $values = $form_state->getValues();
        array_pop($parents);
        // If it is a button to add new action, prepare the new form element.
        if ($triggering_element['#name'] === $field_definition->getName() . '_add_field_widget_actions') {
          $element['#open'] = TRUE;
          $parents[] = 'action';
          $action_plugin_id = NestedArray::getValue($values, $parents);
          $uuid = $this->uuid->generate();
          $enabled_plugins[$uuid] = ['plugin_id' => $action_plugin_id, 'open' => TRUE];
        }
        // If it is a button to remove an action, delete the corresponding form
        // element.
        if (str_contains($triggering_element['#name'], $field_definition->getName() . '_remove_field_widget_action')) {
          $element['#open'] = TRUE;
          $action_to_remove = array_pop($parents);
          $enabled_plugins = NestedArray::getValue($values, $parents);
          if (isset($enabled_plugins['new'])) {
            unset($enabled_plugins['new']);
          }
          if (isset($enabled_plugins[$action_to_remove])) {
            unset($enabled_plugins[$action_to_remove]);
          }
        }
        // In case the form display form is saved, make sure only needed actions
        // are in the list.
        if ($triggering_element['#name'] === $field_definition->getName() . '_plugin_settings_update') {
          array_pop($parents);
          $parents[] = 'third_party_settings';
          $parents[] = 'field_widget_actions';
          $enabled_plugins = NestedArray::getValue($values, $parents);
          if (isset($enabled_plugins['new'])) {
            unset($enabled_plugins['new']);
          }
        }
      }
      if (empty($enabled_plugins)) {
        // NULL (can be a result of NestedArray::getValue) is also empty, but we
        // need to make sure that it is possible to iterate through this
        // variable as it is used in foreach later.
        $enabled_plugins = [];
        // If there are no actions, no need to show the information about their
        // sorting.
        $element['new']['add']['#suffix'] = '';
      }
      $i = 0;
      foreach ($enabled_plugins as $action_id => $configuration) {
        if (empty($configuration['plugin_id'])) {
          continue;
        }
        try {
          /** @var \Drupal\field_widget_actions\FieldWidgetActionInterface $allowed_field_widget_action */
          $allowed_field_widget_action = $this->fieldWidgetActionManager->createInstance($configuration['plugin_id'], $configuration);
        }
        catch (PluginNotFoundException $e) {
          continue;
        }
        // Check if the action is a valid field widget action.
        if (!$allowed_field_widget_action instanceof FieldWidgetActionInterface) {
          continue;
        }
        // The field definition does not have bundle set by default.
        if (!$field_definition->getTargetBundle() && $form['#bundle']) {
          // Set the bundle to the field definition.
          $field_definition->setTargetBundle($form['#bundle']);
        }
        $allowed_field_widget_action->setFieldDefinition($field_definition);
        // Check so the field is available for the action.
        if (!$allowed_field_widget_action->isAvailable()) {
          continue;
        }
        $allowed_field_widget_action->setWidget($plugin);
        $element[$action_id] = $allowed_field_widget_action->buildConfigurationForm($form, $form_state, $action_id);
        $element[$action_id]['#type'] = 'details';
        if ($i == 0) {
          // Wrap only action elements, for proper sorting. There is an issue
          // with `filter` property of Sortable, that removes the default on
          // click event and this prevents from using dropdown list of actions.
          $element[$action_id]['#prefix'] = '<div class="field-widget-actions-sortable">';
        }
        // Element for new action. Let's have it opened by default, so it is
        // easily accessible.
        if (!empty($configuration['open'])) {
          $element[$action_id]['#open'] = TRUE;
          $element[$action_id]['weight']['#default_value'] = $i;
        }
        $title = $allowed_field_widget_action->getButtonLabel();
        if ($title != $allowed_field_widget_action->getLabel()) {
          $title .= ' (' . $allowed_field_widget_action->getLabel() . ')';
        }
        $element[$action_id]['#title'] = $title;
        $element[$action_id]['#description'] = $allowed_field_widget_action->getDescription();
        $element[$action_id]['#attributes']['class'][] = 'field-widget-action-element';
        // Do not display "Remove Action" for newly added items, as it is
        // confusing.
        if (empty($configuration['open'])) {
          $element[$action_id]['remove'] = [
            '#type' => 'button',
            '#name' => $field_definition->getName() . '_remove_field_widget_action_' . $action_id,
            '#value' => $this->t('Remove Action'),
            '#ajax' => [
              'callback' => [static::class, 'removeAction'],
              'event' => 'click',
              'wrapper' => $wrapper_id,
            ],
          ];
        }
        $i++;
        if ($i == count($enabled_plugins)) {
          $element[$action_id]['#suffix'] = '</div>';
        }
      }
    }
    return $element;
  }

  /**
   * Adds new action.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The actions element.
   */
  public static function addAction(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    array_pop($array_parents);
    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Remove action.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The actions element.
   */
  public static function removeAction(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    array_pop($array_parents);
    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context) {
    $allowed_actions = $this->fieldWidgetActionManager->getAllowedFieldWidgetActions($context['widget']->getPluginId(), $context['items']->getFieldDefinition()->getType());
    $actions = $context['widget']->getThirdPartySettings('field_widget_actions') ?? [];
    foreach ($actions as $action_id => $action) {
      if (empty($action['plugin_id'])) {
        continue;
      }
      if (empty($action['enabled'])) {
        continue;
      }
      $plugin_id = $action['plugin_id'];
      if (empty($allowed_actions[$plugin_id])) {
        continue;
      }
      $field_widget_action = $this->fieldWidgetActionManager->createInstance($plugin_id, $action);
      if (isset($context['items'])) {
        $field_widget_action->setFieldDefinition($context['items']->getFieldDefinition());
      }
      if ($field_widget_action instanceof FieldWidgetActionInterface && $field_widget_action->isAvailable()) {
        $context['action_id'] = $action_id;
        $field_widget_action->completeFormAlter($field_widget_complete_form, $form_state, $context);
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context) {
    $allowed_actions = $this->fieldWidgetActionManager->getAllowedFieldWidgetActions($context['widget']->getPluginId(), $context['items']->getFieldDefinition()->getType());
    $actions = $context['widget']->getThirdPartySettings('field_widget_actions') ?? [];
    foreach ($actions as $action_id => $action) {
      if (empty($action['plugin_id'])) {
        continue;
      }
      if (empty($action['enabled'])) {
        continue;
      }
      $plugin_id = $action['plugin_id'];
      if (empty($allowed_actions[$plugin_id])) {
        continue;
      }
      $field_widget_action = $this->fieldWidgetActionManager->createInstance($plugin_id, $action);
      if (isset($context['items'])) {
        $field_widget_action->setFieldDefinition($context['items']->getFieldDefinition());
      }
      if ($field_widget_action instanceof FieldWidgetActionInterface && $field_widget_action->isAvailable()) {
        $context['action_id'] = $action_id;
        $field_widget_action->singleElementFormAlter($element, $form_state, $context);
      }
    }
  }

  /**
   * Implements hook_config_actions_alter().
   */
  #[Hook('config_action_alter')]
  public function configActionAlter(array &$definitions) {
    if (empty($definitions['setComponentThirdPartySetting'])) {
      $definitions['setComponentThirdPartySetting'] = [
        'class' => SetupFieldWidgetAction::class,
        'provider' => 'field_widget_actions',
        'id' => 'setComponentThirdPartySetting',
        'admin_label' => new TranslatableMarkup('Setup Field Widget Actions'),
        'entity_types' => [
          'entity_form_display',
        ],
      ];
    }
  }

}

<?php

namespace Drupal\commerce_order\Plugin\Field\FieldWidget;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[FieldWidget(
  id: "commerce_order_items",
  label: new TranslatableMarkup("Order items (Experimental)"),
  field_types: ["entity_reference"],
  multiple_values: TRUE,
)]
class OrderItemsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The list of table fields.
   */
  protected array $tableFields = [];

  /**
   * The element wrapper ID.
   */
  protected string $wrapperId;

  /**
   * The list of parents.
   */
  protected array $parents;

  /**
   * The field name tha uses this widget.
   */
  protected string $fieldName;

  /**
   * The main entity this widget used in.
   */
  protected FieldableEntityInterface $mainEntity;

  /**
   * The current state of the form.
   */
  protected FormStateInterface $formState;

  /**
   * The inline form manager.
   */
  protected InlineFormManager $inlineFormManager;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $defaults = parent::defaultSettings();
    $defaults += [
      'collapsible' => FALSE,
      'collapsed' => FALSE,
      'allow_new' => TRUE,
      'allow_duplicate' => FALSE,
    ];
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $states_prefix = 'fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings]';
    $element['collapsible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsible'),
      '#default_value' => $this->getSetting('collapsible'),
    ];
    $element['collapsed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsed by default'),
      '#default_value' => $this->getSetting('collapsed'),
      '#states' => [
        'visible' => [
          ':input[name="' . $states_prefix . '[collapsible]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['allow_new'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to add new order items.'),
      '#default_value' => $this->getSetting('allow_new'),
    ];
    $element['allow_duplicate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to duplicate order items.'),
      '#default_value' => $this->getSetting('allow_duplicate'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($this->getSetting('collapsible')) {
      $summary[] = $this->getSetting('collapsed') ? $this->t('Collapsible, collapsed by default') : $this->t('Collapsible');
    }
    if ($this->getSetting('allow_new')) {
      $summary[] = $this->t('New order items can be added.');
    }
    else {
      $summary[] = $this->t('New order items cannot be added.');
    }
    if ($this->getSetting('allow_duplicate')) {
      $summary[] = $this->t('Order items can be duplicated.');
    }
    else {
      $summary[] = $this->t('Order items cannot be duplicated.');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    // Only for the 'commerce_order_item' reference fields.
    return $field_definition->getItemDefinition()->getSetting('target_type') === 'commerce_order_item';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    $this->wrapperId = 'order-items-wrapper_' . $items->getName();
    $this->mainEntity = $items->getEntity();

    $this->prepareFormState($form_state, $items);
    $this->formState = $form_state;
    $this->parents = array_merge($element['#field_parents'], [
      $items->getName(),
      'form',
    ]);
    $this->fieldName = $items->getName();
    $element = [
      '#type' => $this->getSetting('collapsible') ? 'details' : 'fieldset',
      '#description' => $this->getFilteredDescription(),
      '#prefix' => '<div id="' . $this->wrapperId . '">',
      '#suffix' => '</div>',
      '#field_title' => $this->fieldDefinition->getLabel(),
      '#oiw_root' => TRUE,
      '#oiw_field_name' => $items->getName(),
      '#process' => [[static::class, 'processElement']],
    ] + $element;
    if ($element['#type'] == 'details') {
      // If there's user input, keep the details open. Otherwise, use settings.
      $element['#open'] = $form_state->getUserInput() ?: !$this->getSetting('collapsed');
    }

    $element['#element_validate'][] = [get_class($this), 'updateRowWeights'];

    $this->prepareTableFields();
    $element['table'] = $this->getOrderItemsTable($items);
    $settings = $this->getSettings();
    $order_items_types = $this->getListOfBundles();
    $allow_new = $settings['allow_new'] && !empty($order_items_types);

    // If no form is open, show buttons that open one.
    $open_form = $form_state->get([
      'order_items_widget_state',
      $this->fieldName,
      'form',
    ]);

    if ($allow_new) {
      $element['add_new_item'] = match ($open_form) {
        'add' => $this->buildAddNewItemForm($order_items_types),
        default => $this->buildAddNewItemAction($order_items_types),
      };
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    if (empty($triggering_element['#oiw_main_entity_submit'])) {
      return;
    }

    $field_name = $this->fieldDefinition->getName();
    $widget_state = $form_state->get(['order_items_widget_state', $field_name]);
    $widget_state_items = $widget_state['items'] ?? [];
    usort($widget_state_items, [SortArray::class, 'sortByWeightElement']);
    $values = [];
    foreach ($widget_state_items as $item) {
      $values[] = [
        'entity' => $item['entity'],
      ];
    }

    if ($widget_state['form'] === 'add') {
      $element = NestedArray::getValue($form, [$field_name, 'widget', 'add_new_item']);
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $element['#inline_form'];
      $values[] = [
        'entity' => $inline_form->getEntity(),
      ];
    }

    // Check open duplicate forms and set entity to the list.
    foreach ($widget_state['items'] as $delta => $item) {
      if ($item['form'] !== 'duplicate') {
        continue;
      }
      $parents = [$field_name, 'widget', 'table', 'duplicate_' . $delta, 0, 'form', 'inline_form'];
      $element = NestedArray::getValue($form, $parents);
      if (!$element || !isset($element['#inline_form'])) {
        continue;
      }

      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $element['#inline_form'];
      $values[] = [
        'entity' => $inline_form->getEntity(),
      ];
    }

    $values = $this->massageFormValues($values, $form, $form_state);
    // Assign the values and remove the empty ones.
    $items->setValue($values);
    $items->filterEmptyItems();
  }

  /**
   * Process callback to add submit to the main form.
   *
   * @param array $element
   *   The form element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $form
   *   The complete form structure.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, array &$form): array {
    if (isset($form['#oiw_element_submit_attached'])) {
      return $element;
    }
    $form['#oiw_element_submit_attached'] = TRUE;
    // Add submit to save updated/added order items and remove deleted.
    // Entity form actions.
    $submit = [get_called_class(), 'submitEntityForm'];
    foreach (['submit', 'publish', 'unpublish'] as $action) {
      if (!empty($form['actions'][$action])) {
        $form['actions'][$action]['#submit'] = array_merge([$submit], $form['actions'][$action]['#submit']);
        $form['actions'][$action]['#oiw_main_entity_submit'] = TRUE;
      }
    }
    // Generic submit button.
    if (!empty($form['submit'])) {
      $form['submit']['#submit'] = array_merge([$submit], $form['submit']['#submit']);
      $form['submit']['#oiw_main_entity_submit'] = TRUE;
    }

    return $element;
  }

  /**
   * Returns the table render array with items.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items
   *   Array of default values for this field.
   */
  protected function getOrderItemsTable(EntityReferenceFieldItemListInterface $items): array {
    $header = [];
    if ($this->isOrderItemsTableDraggable()) {
      $header[] = ['data' => '', 'class' => ['order-items-tabledrag-header']];
      $header[] = [
        'data' => $this->t('Sort order'),
        'class' => ['order-items-sort-order-header'],
      ];
    }
    foreach ($this->tableFields as $field) {
      $header[] = ['data' => $field['label']];
    }
    $header[] = $this->t('Operations');

    $rows = [];
    $widget_items = $this->formState->get([
      'order_items_widget_state',
      $items->getName(),
      'items',
    ]);
    uasort($widget_items, [SortArray::class, 'sortByWeightElement']);
    foreach ($widget_items as $delta => $widget_item) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $widget_item['entity'];
      $rows[] = $this->prepareOrderItemRow($order_item, $delta);
      if (empty($widget_item['form'])) {
        continue;
      }
      $form = match ($widget_item['form']) {
        'edit' => $this->buildEditForm($order_item, $delta),
        'duplicate' => $this->buildDuplicateForm($order_item, $delta),
        'remove' => $this->buildRemoveForm($order_item, $delta),
        default => [],
      };
      $rows[$widget_item['form'] . '_' . $delta] = [
        [
          'form' => [
            '#type' => 'container',
            'inline_form' => $form,
          ],
          '#wrapper_attributes' => ['colspan' => count($this->tableFields) + 2],
        ],
      ];
    }

    if (!empty($rows)) {
      $tabledrag = [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'order-item-delta',
        ],
      ];

      return [
        '#type' => 'table',
        '#header' => $header,
        '#tabledrag' => $tabledrag,
      ] + $rows;
    }
    return [];
  }

  /**
   * Returns the row for the order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param int $delta
   *   The order of this item.
   */
  protected function prepareOrderItemRow(OrderItemInterface $order_item, int $delta): array {
    $cells = [];
    $row_classes = ['order-item-row'];
    if ($this->isOrderItemsTableDraggable()) {
      $cells[] = [
        'data' => ['#plain_text' => ''],
        '#wrapper_attributes' => ['class' => ['order-item-tabledrag-handle']],
      ];
      $cells[] = [
        'data' => [
          '#type' => 'weight',
          '#title_display' => 'invisible',
          '#delta' => $delta,
          '#default_value' => $delta,
          '#attributes' => ['class' => ['order-item-delta']],
          '#parents' => array_merge($this->parents, [$delta, 'delta']),
        ],
      ];
      $row_classes[] = 'draggable';
    }
    foreach ($this->tableFields as $field_name => $field) {
      if ($field['type'] == 'label') {
        $label = ['#markup' => $order_item->label()];
        if ($order_item->getPurchasedEntity() instanceof ProductVariationInterface) {
          $label = [
            '#type' => 'inline_template',
            '#template' => '{{ label }}<br /><span style="{{ style }}">SKU: {{ sku }}</span>',
            '#context' => [
              'label' => $order_item->label(),
              'sku' => $order_item->getPurchasedEntity()->getSku(),
              'style' => 'color: var(--commerce-color--neutral); font-size: .85em',
            ],
          ];
        }
        $cells[$field_name] = $label;
      }
      elseif ($field['type'] == 'field' && $order_item->hasField($field_name)) {
        $display_options = ['label' => 'hidden'];
        if (isset($field['display_options'])) {
          $display_options += $field['display_options'];
        }
        $cells[$field_name] = $order_item->get($field_name)
          ->view($display_options);
      }
      else {
        $cells[$field_name] = ['#markup' => $this->t('N/A')];
      }
    }

    if (empty($this->formState->get([
      'order_items_widget_state',
      $this->fieldName,
      'items',
      $delta,
      'form',
    ]))) {
      $cells['actions'] = $this->getRowActions($order_item, $delta);
    }

    return $cells + ['#attributes' => ['class' => $row_classes]];
  }

  /**
   * Returns the list of actions per row.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param int $delta
   *   The order of this item.
   */
  protected function getRowActions(OrderItemInterface $order_item, int $delta): array {
    $actions = [
      '#type' => 'container',
      '#attributes' => ['class' => ['order-item-operations']],
    ];
    $name_prefix = "order-item-{$this->fieldName}";
    if ($this->getSetting('allow_duplicate') && !empty($this->getListOfBundles())) {
      $actions['duplicate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Duplicate'),
        '#name' => "$name_prefix-duplicate-$delta",
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'inlineOrderItemFormGetElement'],
          'wrapper' => $this->wrapperId,
        ],
        '#submit' => [[get_class($this), 'submitOpenRowForm']],
        '#oiw_row_form' => 'duplicate',
        '#oiw_row_delta' => $delta,
        '#oiw_field_name' => $this->fieldName,
      ];
    }
    if ($order_item->isNew() || $order_item->access('update')) {
      $actions['edit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit'),
        '#name' => "$name_prefix-edit-$delta",
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'inlineOrderItemFormGetElement'],
          'wrapper' => $this->wrapperId,
        ],
        '#submit' => [[get_class($this), 'submitOpenRowForm']],
        '#oiw_row_form' => 'edit',
        '#oiw_row_delta' => $delta,
        '#oiw_field_name' => $this->fieldName,
      ];
    }
    if ($order_item->access('delete')) {
      $actions['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => "$name_prefix-remove-$delta",
        '#attributes' => ['class' => ['button--danger']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'inlineOrderItemFormGetElement'],
          'wrapper' => $this->wrapperId,
        ],
        '#submit' => [[get_class($this), 'submitOpenRowForm']],
        '#oiw_row_form' => 'remove',
        '#oiw_row_delta' => $delta,
        '#oiw_field_name' => $this->fieldName,
      ];
    }

    return $actions;
  }

  /**
   * Updates entity weights based on their weights in the widget.
   *
   * @param array $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $form
   *   The complete form structure.
   */
  public static function updateRowWeights(array $element, FormStateInterface $form_state, array $form): void {
    $field_name = $element['#oiw_field_name'];
    $items = $form_state->get(['order_items_widget_state', $field_name, 'items']);
    foreach ($items as $delta => &$item) {
      $item['weight'] = $form_state->getValue(array_merge($element['#parents'], ['form', $delta, 'delta']));
    }
    $form_state->set(['order_items_widget_state', $field_name, 'items'], $items);
  }

  /**
   * Submit callback to open row form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitOpenRowForm(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->setRebuild();
    $form_state->set(
      [
        'order_items_widget_state',
        $triggering_element['#oiw_field_name'],
        'items',
        $triggering_element['#oiw_row_delta'],
        'form',
      ],
      $triggering_element['#oiw_row_form']
    );
  }

  /**
   * Submit callback to open inline form for new item.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitOpenForm(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->setRebuild();
    $form_state->set(
      [
        'order_items_widget_state',
        $triggering_element['#oiw_field_name'],
        'form',
      ],
      $triggering_element['#oiw_form']
    );
  }

  /**
   * Submit callback to close row form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitCloseRowForm(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->setRebuild();
    $form_state->set(
      [
        'order_items_widget_state',
        $triggering_element['#oiw_field_name'],
        'items',
        $triggering_element['#oiw_row_delta'],
        'form',
      ],
      NULL
    );
  }

  /**
   * Submit callback to add item to the removal list.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitConfirmRemove(array $form, FormStateInterface $form_state): void {
    $remove_button = $form_state->getTriggeringElement();
    $delta = $remove_button['#oiw_row_delta'];
    $field_name = $remove_button['#oiw_field_name'];
    $widget_state = $form_state->get(['order_items_widget_state', $field_name]);
    $order_item = $widget_state['items'][$delta]['entity'];
    unset($widget_state['items'][$delta]);
    if (!$order_item->isNew()) {
      $widget_state['delete'][] = $order_item;
    }
    $form_state->setRebuild();
    $form_state->set(['order_items_widget_state', $field_name], $widget_state);
  }

  /**
   * Submit callback to close all row forms.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitCloseChildForms(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#oiw_field_name'];
    $widget_state = $form_state->get(['order_items_widget_state', $field_name]);
    $widget_state['form'] = NULL;
    foreach ($widget_state['items'] as &$item) {
      $item['form'] = NULL;
    }
    $form_state->setRebuild();
    $form_state->set(['order_items_widget_state', $field_name], $widget_state);
  }

  /**
   * Submit callback triggered with the main form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitEntityForm(array $form, FormStateInterface $form_state): void {
    $widget_states =& $form_state->get('order_items_widget_state');
    foreach ($widget_states as &$widget_state) {
      foreach ($widget_state['items'] as &$item) {
        if (!empty($item['entity']) && $item['needs_save']) {
          $item['entity']->save();
          $item['needs_save'] = FALSE;
        }
      }
      foreach ($widget_state['delete'] as $entity) {
        $entity->delete();
      }
      $widget_state['delete'] = [];
    }
  }

  /**
   * Submit callback for the inline form save action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function submitSaveEntity(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -2);
    while (!isset($element['#inline_form'])) {
      $element = NestedArray::getValue($form, $array_parents);
      array_pop($array_parents);
    }

    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $element['#inline_form'];
    $delta = $element['#oiw_row_delta'];
    $field_name = $element['#oiw_field_name'];
    $entity = $inline_form->getEntity();
    $items = $form_state->get(['order_items_widget_state', $field_name, 'items']);
    if (in_array($element['#op'], ['add', 'duplicate'])) {
      // Determine the correct weight of the new element.
      $weight = 0;
      if (!empty($items)) {
        $weight = max(array_keys($items)) + 1;
      }
      $items[] = [
        'entity' => $entity,
        'weight' => $weight,
        'form' => NULL,
        'needs_save' => $entity->isNew(),
      ];
    }
    else {
      $items[$delta]['needs_save'] = TRUE;
      $items[$delta]['entity'] = $entity;
    }
    $items[$delta]['form'] = NULL;
    $form_state->setRebuild();
    $form_state->set(['order_items_widget_state', $field_name, 'items'], $items);
    $form_state->set(['order_items_widget_state', $field_name, 'form'], NULL);
  }

  /**
   * Returns the root widget element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function inlineOrderItemFormGetElement(array $form, FormStateInterface $form_state): array {
    $element = [];
    $triggering_element = $form_state->getTriggeringElement();

    // Remove the action and the actions' container.
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -2);

    while (!isset($element['#oiw_root'])) {
      $element = NestedArray::getValue($form, $array_parents);
      array_pop($array_parents);
    }

    return $element;
  }

  /**
   * Replaces the entity autocomplete field.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   */
  public static function getPurchasedEntityAutocomplete(array $form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();

    // Remove the action and the actions' container.
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $array_parents = array_merge($array_parents, ['entity_selector']);

    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Returns the order item edit inline form.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param int $delta
   *   The order of this item.
   */
  protected function buildEditForm(OrderItemInterface $order_item, int $delta): array {
    $inline_form = $this->inlineFormManager->createInstance('order_item', [
      'form_mode' => 'edit',
      'operation' => 'edit',
    ], $order_item);
    $form['order_item'] = [
      '#parents' => array_merge($this->parents, ['order_item_inline_form', $delta, 'form']),
      '#process' => [[get_class($this), 'buildEntityFormActions']],
      '#oiw_row_delta' => $delta,
      '#oiw_wrapper' => $this->wrapperId,
      '#oiw_field_name' => $this->fieldName,
    ];
    return $inline_form->buildInlineForm($form['order_item'], $this->formState);
  }

  /**
   * Returns the order item edit inline form.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param int $delta
   *   The order of this item.
   */
  protected function buildDuplicateForm(OrderItemInterface $order_item, int $delta): array {
    $duplicate_item = $order_item->createDuplicate();
    $inline_form = $this->inlineFormManager->createInstance('order_item', ['operation' => 'duplicate'], $duplicate_item);
    $form['duplicate_order_item'] = [
      '#parents' => array_merge($this->parents, ['duplicate_order_item_inline_form', $delta, 'form']),
      '#process' => [[get_class($this), 'buildEntityFormActions']],
      '#oiw_row_delta' => $delta,
      '#oiw_wrapper' => $this->wrapperId,
      '#oiw_field_name' => $this->fieldName,
    ];
    return $inline_form->buildInlineForm($form['duplicate_order_item'], $this->formState);
  }

  /**
   * Returns the order item removal form.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param int $delta
   *   The order of this item.
   */
  protected function buildRemoveForm(OrderItemInterface $order_item, int $delta): array {
    $name_prefix = "order-item-remove-{$this->fieldName}";
    $form['message'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $this->t('Are you sure you want to remove %label?', ['%label' => $order_item->label()]),
    ];
    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];
    $form['actions']['item_confirm_remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#name' => "$name_prefix-confirm-$delta",
      '#attributes' => ['class' => ['button--danger']],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_class($this), 'inlineOrderItemFormGetElement'],
        'wrapper' => $this->wrapperId,
      ],
      '#submit' => [[get_class($this), 'submitConfirmRemove']],
      '#oiw_row_delta' => $delta,
      '#oiw_field_name' => $this->fieldName,
    ];
    $form['actions']['item_cancel_remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => "$name_prefix-cancel-$delta",
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_class($this), 'inlineOrderItemFormGetElement'],
        'wrapper' => $this->wrapperId,
      ],
      '#submit' => [[get_class($this), 'submitCloseRowForm']],
      '#oiw_row_delta' => $delta,
      '#oiw_field_name' => $this->fieldName,
    ];

    return $form;
  }

  /**
   * Returns the action to add new item.
   *
   * @param array $order_item_types
   *   The list of order item bundles.
   */
  protected function buildAddNewItemAction(array $order_item_types): array {
    $purchasable_entity_types = $this->getPurchasableEntityTypes($order_item_types);
    if (empty($purchasable_entity_types)) {
      return [];
    }

    $show_type_selector = count($purchasable_entity_types) > 1;
    $entity_selection_wrapper_id = sprintf('%s-entity-selection', $this->wrapperId);

    // Get the purchased entity type from form values. If not set, use the
    // first available type.
    $triggering_element = $this->formState->getTriggeringElement();

    $parents = $triggering_element['#parents'] ?? [];
    if (end($parents) === 'type_selector') {
      $selected_entity_type = $this->formState->getValue($parents);
    }
    else {
      $selected_entity_type = reset($purchasable_entity_types);
    }

    // If multiple purchased entity types are available, display a select that
    // lets the user choose which type should be used first. Based on the
    // selection, the AJAX callback will dynamically add an entity autocomplete
    // field configured for the chosen entity type.
    $type_selector = [];
    if ($show_type_selector) {
      // Generates options of purchased entity types for the order.
      $type_options = [];
      foreach ($purchasable_entity_types as $purchasable_entity_type) {
        try {
          $definition = $this->entityTypeManager->getDefinition($purchasable_entity_type);
          $type_options[$purchasable_entity_type] = $definition->getLabel();
        }
        catch (PluginNotFoundException) {
          continue;
        }
      }
      $type_selector = [
        '#type' => 'select',
        '#title' => $this->t('Purchased entity type'),
        '#title_display' => 'invisible',
        '#options' => $type_options,
        '#required' => TRUE,
        '#default_value' => $selected_entity_type,
        '#ajax' => [
          'callback' => [get_class($this), 'getPurchasedEntityAutocomplete'],
          'wrapper' => $entity_selection_wrapper_id,
          'event' => 'change',
        ],
        '#access' => count($type_options) > 1,
      ];
    }

    $bundles = [];
    if ($selected_entity_type === 'commerce_product_variation') {
      $variation_type_storage = $this->entityTypeManager->getStorage('commerce_product_variation_type');
      /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface[] $variation_types */
      $variation_types = $variation_type_storage->loadByProperties([
        'orderItemType' => $order_item_types,
      ]);
      if (empty($variation_types)) {
        return [];
      }

      foreach ($variation_types as $variation_type) {
        $bundles[] = $variation_type->id();
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityType $entity_type_definition */
    $entity_type_definition = $this->entityTypeManager->getDefinition($selected_entity_type);

    $placeholder = $selected_entity_type == 'commerce_product_variation' ? $this->t('Search by variation title or SKU') : $this->t('Search by entity label');
    $purchasable_entity_item = [
      '#type' => 'entity_autocomplete',
      '#title' => $entity_type_definition->getLabel(),
      '#title_display' => 'invisible',
      '#placeholder' => $placeholder,
      '#target_type' => $selected_entity_type,
      '#selection_handler' => 'default',
      '#maxlength' => 1024,
    ];

    // Add bundle settings.
    if (!empty($bundles)) {
      $purchasable_entity_item['#selection_settings']['target_bundles'] = $bundles;
    }

    return [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => ['class' => ['container-inline']],
      'type_selector' => $type_selector,
      'entity_selector' => [
        '#type' => 'container',
        '#prefix' => '<div id="' . $entity_selection_wrapper_id . '">',
        '#suffix' => '</div>',
        'purchasable_entity' => $purchasable_entity_item,
        'oiw_add_new_item_submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Add new order item'),
          '#name' => 'oiw-' . $this->fieldName . '-add',
          '#limit_validation_errors' => [[$this->fieldName, 'add_new_item']],
          '#ajax' => [
            'callback' => [get_class($this), 'inlineOrderItemFormGetElement'],
            'wrapper' => $this->wrapperId,
          ],
          '#submit' => [[get_class($this), 'submitOpenForm']],
          '#oiw_form' => 'add',
          '#oiw_field_name' => $this->fieldName,
        ],
      ],
    ];
  }

  /**
   * Returns inline form for the new item.
   */
  protected function buildAddNewItemForm(array $order_item_types): array {
    $fallback_form = $this->buildAddNewItemAction($order_item_types);
    if (empty($fallback_form)) {
      return $fallback_form;
    }
    $triggering_element = $this->formState->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    if (end($parents) !== 'oiw_add_new_item_submit') {
      return $fallback_form;
    }

    // Remove triggering element from parents list.
    $parents = array_slice($parents, 0, -2);
    $purchasable_entity_type = $this->formState->getValue(array_merge($parents, ['type_selector']));
    $purchased_entity_id = $this->formState->getValue(array_merge($parents, ['entity_selector', 'purchasable_entity']));

    // When the entity ID is not provided return fallback form.
    if (!$purchased_entity_id) {
      return $fallback_form;
    }

    // For missing type selector get the first available entity type.
    $purchasable_entity_types = $this->getPurchasableEntityTypes($order_item_types);
    if (empty($purchasable_entity_type)) {
      $purchasable_entity_type = reset($purchasable_entity_types);
    }

    // Get purchasable entity.
    /** @var \Drupal\Core\Entity\ContentEntityInterface|null $purchasable_entity */
    $purchasable_entity = $this->entityTypeManager->getStorage($purchasable_entity_type)->load($purchased_entity_id);
    if (!($purchasable_entity instanceof PurchasableEntityInterface)) {
      return $fallback_form;
    }

    // Create order item based on the selected entity.
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $new_item = $order_item_storage->createFromPurchasableEntity($purchasable_entity);
    $inline_form = $this->inlineFormManager->createInstance('order_item', ['operation' => 'add'], $new_item);
    $items = $this->formState->get(['order_items_widget_state', $this->fieldName, 'items']);
    $delta = 0;
    if (!empty($items)) {
      $delta = max(array_keys($items)) + 1;
    }
    $new_order_item = [
      '#parents' => array_merge($this->parents, [$delta]),
      '#process' => [[get_class($this), 'buildEntityFormActions']],
      '#oiw_row_delta' => $delta,
      '#oiw_wrapper' => $this->wrapperId,
      '#oiw_field_name' => $this->fieldName,
    ];
    return $inline_form->buildInlineForm($new_order_item, $this->formState);
  }

  /**
   * Process the order item inline form to add actions.
   *
   * @param array $inline_form
   *   The processed inline form.
   */
  public static function buildEntityFormActions(array $inline_form): array {
    $label = match ($inline_form['#op']) {
      'edit' => t('Update order item'),
      'duplicate' => t('Duplicate order item'),
      default => t('Create order item'),
    };
    $delta = $inline_form['#oiw_row_delta'];
    $inline_form['actions'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];
    $field_name = $inline_form['#oiw_field_name'];
    $name_prefix = "oiw_$field_name-{$inline_form['#op']}";
    $inline_form['actions']['oiw_' . $inline_form['#op'] . '_save'] = [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => $name_prefix . '-submit-' . $delta,
      '#attributes' => ['class' => ['button--primary']],
      '#limit_validation_errors' => [$inline_form['#parents']],
      '#ajax' => [
        'callback' => [get_called_class(), 'inlineOrderItemFormGetElement'],
        'wrapper' => $inline_form['#oiw_wrapper'],
      ],
      '#submit' => [[get_called_class(), 'submitSaveEntity']],
      '#oiw_field_name' => $field_name,
    ];
    $inline_form['actions']['ief_' . $inline_form['#op'] . '_cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => $name_prefix . '-cancel-' . $delta,
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_called_class(), 'inlineOrderItemFormGetElement'],
        'wrapper' => $inline_form['#oiw_wrapper'],
      ],
      '#submit' => [[get_called_class(), 'submitCloseChildForms']],
      '#oiw_field_name' => $field_name,
    ];
    return $inline_form;
  }

  /**
   * Prepares the form state for the current widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values.
   */
  protected function prepareFormState(FormStateInterface $form_state, FieldItemListInterface $items): void {
    $widget_state = $form_state->get(['order_items_widget_state', $items->getName()]);
    if (empty($widget_state)) {
      $widget_state = [
        'form' => NULL,
        'delete' => [],
        'items' => [],
      ];
      foreach ($items->referencedEntities() as $delta => $entity) {
        $widget_state['items'][$delta] = [
          'entity' => $entity,
          'weight' => $delta,
          'form' => NULL,
          'needs_save' => $entity->isNew(),
        ];
      }
      $form_state->set(['order_items_widget_state', $items->getName()], $widget_state);
    }
  }

  /**
   * Sets list of table fields.
   */
  protected function prepareTableFields(): void {
    if (empty($this->tableFields)) {
      $this->tableFields['label'] = [
        'type' => 'label',
        'label' => $this->t('Title'),
        'weight' => 1,
      ];
      $this->tableFields['unit_price'] = [
        'type' => 'field',
        'label' => $this->t('Unit price'),
        'weight' => 2,
      ];
      $this->tableFields['quantity'] = [
        'type' => 'field',
        'label' => $this->t('Quantity'),
        'weight' => 3,
      ];
    }
  }

  /**
   * Returns TRUE when table has to be draggable.
   */
  protected function isOrderItemsTableDraggable(): bool {
    foreach ($this->formState->get(['order_items_widget_state', $this->fieldName, 'items']) as $data) {
      if ($data['form']) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Return the bundle list of order items which user can create.
   */
  protected function getListOfBundles(): array {
    $bundles = [];
    $order_item_type_storage = $this->entityTypeManager->getStorage('commerce_order_item_type');
    /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface[] $order_item_types */
    if ($this->mainEntity instanceof OrderInterface) {
      $order_item_types = $order_item_type_storage->loadByProperties([
        'orderType' => $this->mainEntity->bundle(),
      ]);
    }
    else {
      $order_item_types = $order_item_type_storage->loadMultiple();
    }
    $access_handled = $this->entityTypeManager->getAccessControlHandler('commerce_order_item');
    foreach ($order_item_types as $order_item_type) {
      if ($access_handled->createAccess($order_item_type->id())) {
        $bundles[] = $order_item_type->id();
      }
    }

    return $bundles;
  }

  /**
   * Returns the list of used purchasable entity types.
   *
   * @param array $order_item_types
   *   The order item types.
   */
  private function getPurchasableEntityTypes(array $order_item_types): array {
    if (empty($order_item_types)) {
      return [];
    }

    $purchasable_entity_types = [];
    $order_item_type_storage = $this->entityTypeManager->getStorage('commerce_order_item_type');

    /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface $order_item_type */
    foreach ($order_item_type_storage->loadMultiple($order_item_types) as $order_item_type) {
      $purchasable_entity_types[] = $order_item_type->getPurchasableEntityTypeId();
    }
    $purchasable_entity_types = array_unique($purchasable_entity_types);

    // Make sure that the "commerce_product_variation" type is always first.
    $key = array_search('commerce_product_variation', $purchasable_entity_types);
    if ($key !== FALSE) {
      unset($purchasable_entity_types[$key]);
      array_unshift($purchasable_entity_types, 'commerce_product_variation');
    }

    return $purchasable_entity_types;
  }

}

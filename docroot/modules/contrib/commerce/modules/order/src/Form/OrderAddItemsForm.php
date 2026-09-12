<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Button;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides a form to add new order items to the order.
 */
class OrderAddItemsForm extends OrderStaticDisplayFormBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    // Clear 'order_items' so the widget only adds new items.
    $form_state->set('items', $this->entity->get('order_items')->getValue());
    $this->entity->set('order_items', NULL);
    parent::init($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form['#title'] = $this->t('Add order items');

    // Update the "Order items" widget.
    $this->updateOrderItemsWidget($form['order_items']['widget']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $value = [];
    $old_values = $form_state->get('items');
    if (!empty($old_values)) {
      $value = $old_values;
    }
    $value = array_merge($value, $this->entity->get('order_items')->getValue());
    $this->entity->set('order_items', $value);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequiredComponents(): array {
    return [
      'order_items' => [
        'type' => 'commerce_order_items',
        'settings' => [
          'draggable' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderOrderItemAction'];
  }

  /**
   * Modifies the "Order items" widget for this form.
   *
   * @param array $element
   *   The order items widget render array.
   */
  protected function updateOrderItemsWidget(array &$element): void {
    $add_item = &$element['add_new_item'];
    $items_table = &$element['table'];
    $has_items_in_table = !empty($items_table);

    // Remove inline class to move button below the input.
    if (!empty($add_item['#attributes']['class'])) {
      $key = array_search('container-inline', $add_item['#attributes']['class']);
      if ($key !== FALSE) {
        unset($add_item['#attributes']['class'][$key]);
      }
    }

    // Modify label and classes for action button that creates a new order item.
    if (isset($add_item['entity_selector'])) {
      $new_item_submit = &$add_item['entity_selector']['oiw_add_new_item_submit'];
      $new_item_submit['#value'] = $has_items_in_table ? $this->t('Add another') : $this->t('Next');
      $new_item_submit['#attributes']['class'][] = 'continue';
    }

    // When the actual order item form is rendered, we need to add the process,
    // as the default order item widget uses it to add action buttons.
    if (isset($add_item['#inline_form'])) {
      $add_item['#process'][] = [get_class($this), 'processOrderItemsWidget'];
    }

    // Update order items table actions.
    $hide_add_new = FALSE;
    if ($has_items_in_table) {
      foreach (Element::children($items_table) as $key) {
        if (str_starts_with((string) $key, 'edit_')) {
          $hide_add_new = TRUE;
        }
        elseif (str_starts_with((string) $key, 'remove_')) {
          $remove_form = &$items_table[$key][0]['form']['inline_form'];
          $remove_action = &$remove_form['actions']['item_confirm_remove'];
          $hide_add_new = TRUE;
        }
        else {
          $actions = &$items_table[$key]['actions'];
          if (!$actions || !isset($actions['edit'], $actions['remove'])) {
            continue;
          }

          // Wrap actions with specific classes.
          $actions['#attributes']['class'][] = 'commerce-order-item-inline-operations';
          foreach (Element::children($actions) as $action_key) {
            $action = &$actions[$action_key];
            $action['#pre_render'][] = [get_class($this), 'preRenderOrderItemAction'];
            $action['#attributes']['class'][] = 'link';
            $action['#prefix'] = '<span class="commerce-order-item-inline-operations__item">';
            $action['#suffix'] = '</span>';
            $action['#value'] = $this->t(strtolower($action['#value']));
          }
          $remove_action = &$actions['remove'];
        }

        // Remove "danger" styling for the "Remove" button.
        if (!isset($remove_action) || empty($remove_action['#attributes']['class'])) {
          continue;
        }
        $class_key = array_search('button--danger', $remove_action['#attributes']['class']);
        if ($class_key !== FALSE) {
          unset($remove_action['#attributes']['class'][$class_key]);
        }
      }
    }

    // Hide the element for new item when we're editing or removing another one.
    $add_item['#access'] = !$hide_add_new;
  }

  /**
   * Processing order item inline form.
   *
   * @param array $inline_form
   *   The order item inline form.
   */
  public static function processOrderItemsWidget(array $inline_form): array {
    if (isset($inline_form['actions']['oiw_add_save'])) {
      $inline_form['actions']['oiw_add_save']['#value'] = t('Add');
      $inline_form['actions']['oiw_add_save']['#attributes']['class'][] = 'continue';
    }
    return $inline_form;
  }

  /**
   * Removes the "button" class for order item actions.
   *
   * @param array $element
   *   The $element with prepared variables.
   */
  public static function preRenderOrderItemAction(array $element): array {
    $element = match ($element['#type']) {
      'submit', 'button' => Button::preRenderButton($element),
      default => $element,
    };

    // Remove "button" class.
    if (empty($element['#attributes']['class'])) {
      return $element;
    }
    $key = array_search('button', $element['#attributes']['class']);
    if ($key !== FALSE) {
      unset($element['#attributes']['class'][$key]);
    }

    return $element;
  }

}

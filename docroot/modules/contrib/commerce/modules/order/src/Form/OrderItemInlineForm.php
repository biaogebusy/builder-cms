<?php

namespace Drupal\commerce_order\Form;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for order items.
 */
class OrderItemInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels() {
    $labels = [
      'singular' => $this->t('order item'),
      'plural' => $this->t('order items'),
    ];
    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $fields = parent::getTableFields($bundles);
    $fields['unit_price'] = [
      'type' => 'field',
      'label' => $this->t('Unit price'),
      'weight' => 2,
    ];
    $fields['quantity'] = [
      'type' => 'field',
      'label' => $this->t('Quantity'),
      'weight' => 3,
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);
    $entity_form['#entity_builders'][] = [get_class($this), 'buildOrderItem'];

    return $entity_form;
  }

  /**
   * Entity builder: populates the order item title from the purchased entity
   * and set the reference to the order being edited.
   *
   * @param string $entity_type
   *   The entity type identifier.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function buildOrderItem($entity_type, OrderItemInterface $order_item, array $form, FormStateInterface $form_state) {
    $purchased_entity = $order_item->getPurchasedEntity();
    if ($order_item->isNew()) {
      if ($purchased_entity) {
        $order_item->setTitle($purchased_entity->getOrderItemTitle());
      }
      $order_item->set('order_id', $form_state->getFormObject()->getEntity());
    }
  }

}

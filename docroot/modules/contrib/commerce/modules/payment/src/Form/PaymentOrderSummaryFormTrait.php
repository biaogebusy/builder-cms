<?php

namespace Drupal\commerce_payment\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a trait for rendering an order summary in payment-related forms.
 */
trait PaymentOrderSummaryFormTrait {

  use StringTranslationTrait;

  /**
   * Builds the order summary section of the payment form.
   *
   * This section includes:
   * - The list of order items in a table.
   * - The total order amount.
   * - The total paid amount.
   * - The remaining balance.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   The updated form array with the order summary included.
   */
  protected function buildOrderSummaryForm(array $form, OrderInterface $order): array {
    $form['order_summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Order details'),
      '#attributes' => ['class' => ['order-summary', 'form-item']],
      '#group' => 'advanced',
      '#open' => count($order->getItems()) < 10,
    ];
    // Render the order items table.
    $form['order_summary']['order_items'] = $this->getOrderItemsTable($order);
    // Render the order total summary.
    $form['order_summary']['total_summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-item']],
      'commerce_order_total_summary' => $order->get('total_price')->view([
        'label' => 'hidden',
        'type' => 'commerce_order_total_summary',
      ]),
    ];
    // Add total paid and balance.
    foreach (['total_paid', 'balance'] as $field_name) {
      $key = sprintf('%s_summary', $field_name);
      $form['order_summary'][$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline', 'text-align-right'],
        ],
        "commerce_order_$field_name" => $order->get($field_name)->view([
          'label' => 'inline',
          'type' => 'commerce_order_total_summary',
        ]),
      ];
    }

    return $form;
  }

  /**
   * Returns the render array for order items table.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  private function getOrderItemsTable(OrderInterface $order): array {
    $item_table = [
      '#type' => 'table',
      '#header' => [
        'title' => $this->t('Title'),
        'quantity' => [
          'data' => $this->t('Quantity'),
          'class' => ['commerce-text--center'],
        ],
        'total' => [
          'class' => ['commerce-text--nowrap'],
          'data' => $this->t('Total price'),
        ],
      ],
      '#attributes' => [
        'class' => ['commerce-table--last-col-right'],
      ],
      '#empty' => $this->t('There are no order items.'),
    ];

    // Generate table rows.
    $rows = [];
    foreach ($order->getItems() as $item) {
      $row['title'] = ['data' => ['#markup' => $item->getTitle()]];
      $row['quantity'] = [
        'data' => $item->get('quantity')->view([
          'type' => 'commerce_quantity',
          'label' => 'hidden',
        ]),
        'class' => ['commerce-text--center'],
      ];
      $row['total'] = [
        'data' => $item->get('total_price')->view(['label' => 'hidden']),
      ];
      $rows[] = $row;
    }
    $item_table['#rows'] = $rows;

    return $item_table;
  }

}

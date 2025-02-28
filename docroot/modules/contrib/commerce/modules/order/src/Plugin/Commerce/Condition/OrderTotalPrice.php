<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the total price condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_total_price",
 *   label = @Translation("Total price"),
 *   display_label = @Translation("Current order total"),
 *   category = @Translation("Order", context = "Commerce"),
 *   entity_type = "commerce_order",
 * )
 */
class OrderTotalPrice extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operator' => '>',
      'amount' => NULL,
      'type' => 'total',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $amount = $this->configuration['amount'];
    // An #ajax bug can cause $amount to be incomplete.
    if (isset($amount) && !isset($amount['number'], $amount['currency_code'])) {
      $amount = NULL;
    }

    $form['operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => $this->getComparisonOperators(),
      '#default_value' => $this->configuration['operator'],
      '#required' => TRUE,
    ];
    $form['amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Amount'),
      '#default_value' => $amount,
      '#required' => TRUE,
    ];
    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Total to compare against'),
      '#options' => [
        'total' => $this->t('Order total'),
        'subtotal' => $this->t('Sum of order item totals'),
      ],
      '#description' => $this->t('Totals used may include prior price adjustments.'),
      '#default_value' => $this->configuration['type'] ?? 'total',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['operator'] = $values['operator'];
    $this->configuration['amount'] = $values['amount'];
    $this->configuration['type'] = $values['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    // Use the order's total or subtotal depending on configuration.
    if (empty($this->configuration['type']) || $this->configuration['type'] === 'total') {
      $total_price = $order->getTotalPrice();
    }
    else {
      $total_price = $order->getSubtotalPrice();
    }
    if (!$total_price) {
      return FALSE;
    }
    $condition_price = Price::fromArray($this->configuration['amount']);
    if ($total_price->getCurrencyCode() != $condition_price->getCurrencyCode()) {
      return FALSE;
    }

    switch ($this->configuration['operator']) {
      case '>=':
        return $total_price->greaterThanOrEqual($condition_price);

      case '>':
        return $total_price->greaterThan($condition_price);

      case '<=':
        return $total_price->lessThanOrEqual($condition_price);

      case '<':
        return $total_price->lessThan($condition_price);

      case '==':
        return $total_price->equals($condition_price);

      default:
        throw new \InvalidArgumentException("Invalid operator {$this->configuration['operator']}");
    }
  }

}

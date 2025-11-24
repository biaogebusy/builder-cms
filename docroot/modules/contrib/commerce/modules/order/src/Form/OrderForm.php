<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderItemType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the commerce_order entity edit forms.
 */
class OrderForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entity;
    $form = parent::form($form, $form_state);

    // Narrow down order item types for the complex IEF widget.
    if (isset($form['order_items']['widget']['actions'], $form['order_items']['widget']['actions']['bundle']) && !empty($form['order_items']['widget']['actions']['bundle']['#options'])) {
      foreach ($form['order_items']['widget']['actions']['bundle']['#options'] as $order_item_type_id => $label) {
        $order_item_type = OrderItemType::load($order_item_type_id);
        if ($order_item_type->getOrderTypeId() !== $order->bundle()) {
          unset($form['order_items']['widget']['actions']['bundle']['#options'][$order_item_type_id]);
        }
      }
    }

    $form['#tree'] = TRUE;
    $form['#theme'] = 'commerce_order_edit_form';
    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $order->getChangedTime(),
    ];
    // Version must be sent to the client, for later overwrite error checking.
    $form['version'] = [
      '#type' => 'hidden',
      '#default_value' => $order->getVersion(),
    ];

    $last_saved = $this->dateFormatter->format($order->getChangedTime(), 'short');
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -100,
      'state' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $order->getState()->getLabel(),
        '#attributes' => [
          'class' => ['entity-meta__title'],
        ],
        // Hide the rendered state if there's a widget for it.
        '#access' => empty($form['store_id']),
      ],
      'completed' => NULL,
      'date' => NULL,
      'changed' => $this->fieldAsReadOnly($this->t('Changed'), $last_saved),
    ];
    $form['customer'] = [
      '#type' => 'details',
      '#title' => $this->t('Customer information'),
      '#group' => 'advanced',
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['order-form-author'],
      ],
      '#weight' => 91,
    ];
    if ($completed_time = $order->getCompletedTime()) {
      $date = $this->dateFormatter->format($completed_time, 'short');
      $form['meta']['completed'] = $this->fieldAsReadOnly($this->t('Completed'), $date);
    }

    if ($placed_time = $order->getPlacedTime()) {
      $date = $this->dateFormatter->format($placed_time, 'short');
      $form['meta']['date'] = $this->fieldAsReadOnly($this->t('Placed'), $date);
    }
    // Show the order's store only if there are multiple available.
    $store_query = $this->entityTypeManager->getStorage('commerce_store')->getQuery()->accessCheck(TRUE);
    $store_count = $store_query->count()->execute();
    if ($store_count > 1) {
      $store_link = $order->getStore()->toLink()->toString();
      $form['meta']['store'] = $this->fieldAsReadOnly($this->t('Store'), $store_link);
    }
    if ($balance = $order->getBalance()) {
      $form['meta']['balance'] = $this->fieldAsReadOnly($this->t('Order balance'), $this->currencyFormatter->format($balance->getNumber(), $balance->getCurrencyCode()));
    }
    // Move uid/mail widgets to the sidebar, or provide read-only alternatives.
    $customer = $order->getCustomer();
    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'customer';
    }
    elseif (!$customer->isAnonymous()) {
      $customer_link = $customer->toLink()->toString();
      $form['customer']['uid'] = $this->fieldAsReadOnly($this->t('Customer'), $customer_link);
    }
    if (isset($form['mail'])) {
      $form['mail']['#group'] = 'customer';

      if ($order->getState()->getId() === 'draft') {
        $form['mail']['#disabled'] = TRUE;
        $form['mail']['widget'][0]['value']['#description'] = $this->t('Cannot be directly set for draft orders; updates based on checkout input or user email address.');
      }
    }
    elseif (!empty($order->getEmail())) {
      $form['customer']['mail'] = $this->fieldAsReadOnly($this->t('Contact email'), $order->getEmail());
    }
    // All additional customer information should come after uid/mail.
    $form['customer']['ip_address'] = $this->fieldAsReadOnly($this->t('IP address'), $order->getIpAddress());

    return $form;
  }

  /**
   * Builds a read-only form element for a field.
   *
   * @param string $label
   *   The element label.
   * @param string $value
   *   The element value.
   *
   * @return array
   *   The form element.
   */
  protected function fieldAsReadOnly($label, $value) {
    return [
      '#type' => 'item',
      '#wrapper_attributes' => [
        'class' => ['container-inline'],
      ],
      '#title' => $label,
      '#markup' => $value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = $this->entity->save();
    $label = $this->entity->label();
    if ($label) {
      $this->messenger()->addStatus($this->t('%label saved.', ['%label' => $this->entity->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Order saved.'));
    }
    $form_state->setRedirect('entity.commerce_order.canonical', ['commerce_order' => $this->entity->id()]);

    return $status;
  }

}

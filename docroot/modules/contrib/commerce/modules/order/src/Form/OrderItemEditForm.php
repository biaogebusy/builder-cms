<?php

namespace Drupal\commerce_order\Form;

use Drupal\commerce\Context;
use Drupal\commerce_price\Price;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the order item edit form.
 */
class OrderItemEditForm extends ContentEntityForm {

  /**
   * The order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $entity;

  /**
   * The chain price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected $chainPriceResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->chainPriceResolver = $container->get('commerce_price.chain_price_resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $purchased_entity = $this->entity->getPurchasedEntity();
    $form['purchased_entity']['#access'] = FALSE;
    if ($purchased_entity) {
      $form['purchased_entity_info'] = [
        '#type' => 'item',
        '#title' => $purchased_entity->getEntityType()->getLabel(),
        '#markup' => $purchased_entity->label(),
        '#weight' => -50,
      ];
    }

    $ajax_action = [
      'callback' => '::updateEstimatedPrice',
      'event' => 'change',
    ];

    // Modify the Unit price and Quantity fields, and add ajax callback.
    $unit_price_widget = &$form['unit_price']['widget'][0];
    $unit_price_widget['override']['#ajax'] = $ajax_action;
    $unit_price_widget['amount']['#ajax'] = $ajax_action;
    $form['quantity']['widget'][0]['value']['#ajax'] = $ajax_action;

    // Show the estimated price that will be changed when the quantity or
    // unit price is modified.
    $estimated_price = $this->getEstimatedPrice($this->entity->getTotalPrice());
    $estimated_price['#prefix'] = '<p id="estimated_price">';
    $estimated_price['#suffix'] = '</p>';
    $form['footer']['estimated_price'] = $estimated_price;

    $form['#attached']['library'][] = 'commerce_order/order-item-edit-form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // Remove "Delete" button.
    $actions['delete']['#access'] = FALSE;

    // For ajax requested form rearrange action buttons.
    if ($this->getRequest()->isXmlHttpRequest()) {
      $actions['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => $this->entity->getOrder()?->toUrl() ?? Url::fromRoute('<front>'),
        '#attributes' => [
          'class' => ['button', 'dialog-cancel'],
        ],
      ];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);
    // Resave related order to update totals.
    $order = $this->entity->getOrder();
    if ($order) {
      $order->save();
      $form_state->setRedirectUrl($order->toUrl());
    }
    return $return;
  }

  /**
   * Ajax callback to refresh the estimated price of order item.
   */
  public function updateEstimatedPrice(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $quantity = $form_state->getValue(['quantity', 0, 'value']);
    if (!empty($quantity)) {
      $this->entity->setQuantity($quantity);
    }
    $this->setOrderItemUnitPrice($form_state);
    $response->addCommand(new HtmlCommand('#estimated_price', $this->getEstimatedPrice($this->entity->getTotalPrice())));
    return $response;
  }

  /**
   * Returns render array for the order item estimated price.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The estimated price object.
   */
  protected function getEstimatedPrice(Price $price): array {
    return [
      '#type' => 'inline_template',
      '#template' => "<b>{{ 'Estimated Price'|t }}: {{ price|commerce_price_format }}</b> <em>({{ 'Price may adjust when order updates'|t }})</em>",
      '#context' => [
        'price' => $price,
      ],
    ];
  }

  /**
   * Sets the unit price in the order item.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function setOrderItemUnitPrice(FormStateInterface $form_state): void {
    if (empty($form_state->getValue(['unit_price', 0, 'override']))) {
      $order = $this->entity->getOrder();
      $time = $order->getCalculationDate()->format('U');
      $context = new Context($order->getCustomer(), $order->getStore(), $time);
      $unit_price = $this->chainPriceResolver->resolve($this->entity->getPurchasedEntity(), $this->entity->getQuantity(), $context);
      $unit_price = $unit_price?->toArray() ?? [];
    }
    else {
      $unit_price = $form_state->getValue(['unit_price', 0, 'amount']);
    }
    if (isset($unit_price['number'], $unit_price['currency_code'])) {
      $unit_price = new Price($unit_price['number'], $unit_price['currency_code']);
      if (!$unit_price->equals($this->entity->getUnitPrice())) {
        $this->entity->setUnitPrice($unit_price, TRUE);
        $this->entity->getTotalPrice();
      }
    }
  }

}

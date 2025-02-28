<?php

namespace Drupal\commerce_payment;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for payments.
 */
class PaymentListBuilder extends EntityListBuilder {

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'payments';

  /**
   * {@inheritdoc}
   *
   * Set limit to false so the list is not paginated.
   */
  protected $limit = FALSE;

  /**
   * The order.
   */
  protected $order;

  /**
   * Constructs a new PaymentListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, CurrencyFormatterInterface $currency_formatter, RouteMatchInterface $route_match, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);

    $this->currencyFormatter = $currency_formatter;
    $this->routeMatch = $route_match;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('commerce_price.currency_formatter'),
      $container->get('current_route_match'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_payments';
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $this->order = $this->routeMatch->getParameter('commerce_order');
    return $this->storage->loadMultipleByOrder($this->order);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = [];
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $entity */
    $payment_gateway = $entity->getPaymentGateway();
    // Add the gateway-specific operations.
    if ($payment_gateway) {
      $operations = $payment_gateway->getPlugin()->buildPaymentOperations($entity);
      // Filter out operations that aren't allowed.
      $operations = array_filter($operations, function ($operation) {
        return !empty($operation['access']);
      });
      // Build the url for each operation.
      $base_route_parameters = [
        'commerce_payment' => $entity->id(),
        'commerce_order' => $entity->getOrderId(),
      ];
      foreach ($operations as $operation_id => $operation) {
        $route_parameters = $base_route_parameters + ['operation' => $operation_id];
        $operation['url'] = new Url('entity.commerce_payment.operation_form', $route_parameters);
        $operations[$operation_id] = $operation;
      }
    }
    // Add the non-gateway-specific operations.
    if ($entity->access('delete')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $entity->toUrl('delete-form'),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Payment');
    $header['state'] = $this->t('State');
    $header['payment_gateway'] = $this->t('Payment gateway');
    $header['authorized'] = $this->t('Authorized');
    $header['completed'] = $this->t('Completed');
    $header['avs_response'] = '';
    $header['remote_id'] = $this->t('Remote ID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $entity */
    $amount = $entity->getAmount();
    $formatted_amount = $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode());
    $refunded_amount = $entity->getRefundedAmount();
    if ($refunded_amount && !$refunded_amount->isZero()) {
      $formatted_amount .= ' ' . $this->t('Refunded:') . ' ';
      $formatted_amount .= $this->currencyFormatter->format($refunded_amount->getNumber(), $refunded_amount->getCurrencyCode());
    }
    $payment_gateway = $entity->getPaymentGateway();

    $row['label'] = $formatted_amount;
    $row['state'] = $entity->getState()->getLabel();
    $row['payment_gateway'] = $payment_gateway ? $payment_gateway->label() : $this->t('N/A');

    foreach (['authorized', 'completed'] as $field) {
      if ($entity->get($field)->isEmpty()) {
        $row[$field] = '';
        continue;
      }
      $row[$field] = $this->dateFormatter->format($entity->get($field)->value, 'short');
    }

    // Add the AVS response code label beneath the gateway name if it exists.
    if ($avs_response_code = $entity->getAvsResponseCode()) {
      $row['avs_response'] = $this->t('AVS response: [@code] @label', ['@code' => $avs_response_code, '@label' => $entity->getAvsResponseCodeLabel()]);
    }
    else {
      $row['avs_response'] = '';
    }

    $row['remote_id'] = $entity->getRemoteId() ?: $this->t('N/A');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Make the payment list page themeable. Add total_paid and order_balance
   * elements.
   */
  public function render() {
    $build = parent::render();
    $build['payment_total_summary'] = [
      '#theme' => 'commerce_payment_total_summary',
      '#order_entity' => $this->order,
    ];
    return $build;
  }

}

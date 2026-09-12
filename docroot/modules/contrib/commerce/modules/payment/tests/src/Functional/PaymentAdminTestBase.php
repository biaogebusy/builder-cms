<?php

namespace Drupal\Tests\commerce_payment\Functional;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

abstract class PaymentAdminTestBase extends CommerceBrowserTestBase {

  /**
   * The admin's order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface $order
   */
  protected $order;

  /**
   * The base admin payment uri.
   */
  protected string $paymentUri;

  /**
   * The currency formatter.
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'administer commerce_payment_gateway',
      'administer commerce_payment',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // An order item type that doesn't need a purchasable entity, for simplicity.
    OrderItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();

    $order_item = $this->createEntity('commerce_order_item', [
      'title' => 'Test order item',
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('10', 'USD'),
    ]);

    $this->order = $this->createEntity('commerce_order', [
      'uid' => $this->loggedInUser->id(),
      'type' => 'default',
      'state' => 'draft',
      'order_items' => [$order_item],
      'store_id' => $this->store,
    ]);

    $this->paymentUri = Url::fromRoute(
      'entity.commerce_payment.collection',
      [
        'commerce_order' => $this->order->id(),
      ],
      [
        'absolute' => TRUE,
      ],
    )->toString();

    $this->currencyFormatter = $this->container->get(
      'commerce_price.currency_formatter'
    );
  }

  /**
   * Confirms that the page contains information about the order.
   *
   * @param bool $contains
   *   Whether ot not the page contain the text.
   */
  public function pageContainsOrderDetails(bool $contains = TRUE) {
    $session = $this->assertSession();
    $method = $contains ? 'pageTextContains' : 'pageTextNotContains';
    foreach ($this->order->getItems() as $item) {
      if (!empty($item->label())) {
        $session->{$method}($item->label());
      }
      $session->{$method}($item->get('unit_price')->formatted);
      $session->{$method}($item->get('total_price')->formatted);
    }
    $subtotal = $this->order->getSubtotalPrice();
    $session->{$method}(
      sprintf(
        'Subtotal %s',
        $this->currencyFormatter->format(
          $subtotal->getNumber(),
          $subtotal->getCurrencyCode()
        )
      )
    );
    $total = $this->order->getTotalPrice();
    $session->{$method}(
      sprintf(
        'Total %s',
        $this->currencyFormatter->format(
          $total->getNumber(),
          $total->getCurrencyCode()
        )
      )
    );
    $balance = $this->order->getBalance();
    $session->{$method}(
      sprintf(
        'Order balance %s',
        $this->currencyFormatter->format(
          $balance->getNumber(),
          $balance->getCurrencyCode()
        )
      )
    );
  }

}

<?php

namespace Drupal\Tests\commerce_order\Functional;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Tests\DrupalTestBrowser;
use GuzzleHttp\Exception\ConnectException;

/**
 * Tests the commerce_order entity forms.
 *
 * @group commerce
 */
class OrderTest extends OrderBrowserTestBase {

  /**
   * Tests creating an order programaticaly and through the UI.
   */
  public function testCreateOrder() {
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_items' => [$order_item],
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
    ]);

    $order_exists = (bool) Order::load($order->id());
    $this->assertNotEmpty($order_exists, 'The new order has been created in the database.');
  }

  /**
   * Tests deleting an order programaticaly and through the UI.
   */
  public function testDeleteOrder() {
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_items' => [$order_item],
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
    ]);
    $order->delete();

    $order_exists = (bool) Order::load($order->id());
    $order_item_exists = (bool) OrderItem::load($order_item->id());
    $this->assertEmpty($order_exists, 'The new order has been deleted from the database.');
    $this->assertEmpty($order_item_exists, 'The matching order item has been deleted from the database.');
  }

  /**
   * Tests load for update locking.
   */
  public function testLoadForUpdate() {
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_items' => [$order_item],
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
    ]);

    /** @var \Drupal\commerce_order\OrderStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $order = $storage->loadForUpdate($order->id());

    $this->drupalGet('commerce/test/resave-no-lock/' . $order->id());
    // Request a page that is also attempting to update the order.
    $this->assertSession()->pageTextContains('Attempted to save order ' . $order->id() . ' that is locked for updating');

    // Set a new client with a short timeout as the following request will
    // wait for the lock.
    $driver = $this->getSession()->getDriver();
    if ($driver instanceof BrowserKitDriver) {
      $client = $driver->getClient();
      if ($client instanceof DrupalTestBrowser) {
        $guzzle_client = $this->container->get('http_client_factory')->fromOptions([
          'timeout' => 1,
          'verify' => FALSE,
        ]);
        $client->setClient($guzzle_client);
      }
    }

    try {
      $this->drupalGet('commerce/test/resave-lock/' . $order->id());
      $this->fail('Request is expected to wait for the lock and time out.');
    }
    catch (ConnectException $e) {

    }

    $order->setData('first_update', 'successful');
    $order->save();

    // Sleep for a second, afterwards the lock is expected to be freed and the
    // test controller can make its change.
    sleep(1);

    $order = $storage->loadUnchanged($order->id());
    $this->assertEquals('successful', $order->getData('first_update'));
    $this->assertEquals('successful', $order->getData('second_update'));
    $this->assertNotEquals('successful', $order->getData('conflicting_update'));
  }

}

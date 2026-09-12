<?php

namespace Drupal\Tests\commerce_log\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\Tests\commerce_order\Functional\OrderBrowserTestBase;

/**
 * Tests the order admin.
 *
 * @group commerce
 * @group commerce_log
 */
class OrderAdminTest extends OrderBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'administer commerce_order_type',
      'access commerce_order overview',
      'add commerce_log commerce_order admin comment',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests adding an order comment.
   */
  public function testAddOrderComment() {
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

    $this->drupalGet($order->toUrl('canonical'));
    $this->assertSession()->pageTextContains('Comment on this order');
    $this->assertSession()->pageTextContains('Your comment will only be visible to users who have access to the activity log.');

    $comments = [
      sprintf('Urgent order for %s!', $this->loggedInUser->getEmail()),
      "Admin's comment<br/><script>alert('Hello!')</script>",
    ];
    $this->getSession()->getPage()->fillField('Comment', $comments[0]);
    $this->getSession()->getPage()->pressButton('Add comment');
    $this->assertSession()->pageTextContainsOnce($comments[0]);

    $this->getSession()->getPage()->fillField('Comment', $comments[1]);
    $this->getSession()->getPage()->pressButton('Add comment');
    $html = $this->getSession()->getPage()->getHtml();
    $this->assertStringContainsString("<p><strong>Admin comment:</strong><br> Admin's comment&lt;br/&gt;&lt;script&gt;alert('Hello!')&lt;/script&gt;</p>", $html);
    $this->assertSession()->pageTextContains($comments[1]);

    // Confirm that comments were not filtered on input.
    /** @var \Drupal\commerce_log\LogStorageInterface $log_storage */
    $log_storage = $this->container->get('entity_type.manager')->getStorage('commerce_log');
    /** @var \Drupal\commerce_log\Entity\LogInterface[] $logs */
    $logs = $log_storage->loadByProperties([
      'template_id' => 'commerce_order_admin_comment',
      'source_entity_type' => 'commerce_order',
      'source_entity_id' => $order->id(),
    ]);
    $this->assertCount(2, $logs);
    foreach (array_values($logs) as $delta => $log) {
      $params = $log->getParams();
      $this->assertStringContainsString($comments[$delta], $params['comment']);
    }
  }

  /**
   * Tests creating an order.
   */
  public function testCreateOrder() {
    // Create an order through the add form.
    $this->drupalGet('/admin/commerce/orders');
    $this->getSession()->getPage()->clickLink('Create a new order');
    $user = $this->loggedInUser->getAccountName() . ' (' . $this->loggedInUser->id() . ')';
    $edit = [
      'customer_type' => 'existing',
      'uid' => $user,
    ];
    $this->submitForm($edit, (string) $this->t('Create'));
    $order = Order::load(1);
    $this->drupalGet($order->toUrl('canonical'));
    $this->assertSession()->pageTextContains('Order created through the order add form.');
  }

}

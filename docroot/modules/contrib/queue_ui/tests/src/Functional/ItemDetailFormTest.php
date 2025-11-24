<?php

namespace Drupal\Tests\queue_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Class ItemDetailFormTest declaration.
 *
 * @package Drupal\Tests\queue_ui\Functional
 * @group queue_ui
 */
class ItemDetailFormTest extends BrowserTestBase {

  /**
   * The theme to install as the default for testing.
   *
   * Defaults to the install profile's default theme, if it specifies any.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * The test runner will merge the $modules lists from this class, the class
   * it extends, and so on up the class hierarchy. It is not necessary to
   * include modules in your list that a parent class has already declared.
   *
   * @var string[]
   *
   * @see \Drupal\Tests\BrowserTestBase::installDrupal()
   */
  protected static $modules = ['queue_ui_order_fixtures'];

  /**
   * Test viewing form of removed queue item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  final public function testMissingItemInTheQueue(): void {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $form_url = Url::fromRoute('queue_ui.inspect.view', [
      'queueName' => 'queue_order_worker_A',
      'queueItem' => 404,
    ]);
    $session = $this->assertSession();
    $this->drupalGet($form_url);
    $session->statusMessageContains('No queue item found with ID 404 under queue queue_order_worker_A');
  }

  /**
   * Test viewing form of removed queue item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  final public function testMissingQueue(): void {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $form_url = Url::fromRoute('queue_ui.inspect.view', [
      'queueName' => 'queue_404',
      'queueItem' => 404,
    ]);
    $session = $this->assertSession();
    $this->drupalGet($form_url);
    $session->statusMessageContains('No queue found with name queue_404');
  }

  /**
   * Test inspect view page.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  final public function testViewPage(): void {
    // Setup.
    $queueName = 'queue_order_worker_A';
    $queue_definition = \Drupal::service('plugin.manager.queue_worker')
      ->getDefinition($queueName);
    $queueTitle = $queue_definition['title'];
    $queue = \Drupal::queue($queueName);
    $queue->createQueue();
    $itemId = $queue->createItem([]);
    $queueUi = \Drupal::service('plugin.manager.queue_ui')
      ->fromQueueName($queueName);
    $queueItem = $queueUi->loadItem($itemId);

    // Scenario.
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $assertSession = $this->assertSession();
    $this->drupalGet(Url::fromRoute('queue_ui.overview_form'));
    $this->clickLink('Inspect', 5);
    $this->clickLink('View');
    $table = [
      'item-id' => ['name' => 'Item ID', 'value' => $itemId],
      'queue-title' => ['name' => 'Queue title', 'value' => $queueTitle],
      'queue-name' => ['name' => 'Queue name', 'value' => $queueName],
      'expire' => ['name' => 'Expire', 'value' => $queueItem->expire],
      'created' => [
        'name' => 'Created',
        'value' => date(DATE_RSS, $queueItem->created),
      ],
      'data' => ['name' => 'Data', 'value' => 'Array ( )'],
    ];
    foreach ($table as $row => $cols) {
      foreach ($cols as $col => $expectation) {
        $assertSession->elementTextContains('css', "[data-queue-ui-view-{$row}-{$col}]", $expectation);
      }
    }
  }

}

<?php

namespace Drupal\Tests\queue_ui\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Class BulkOperationsTest declaration.
 *
 * @package Drupal\Tests\queue_ui\FunctionalJavascript
 * @group queue_ui
 */
class BulkOperationsTest extends WebDriverTestBase {

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
   * @see \Drupal\Tests\WebDriverTestBase::installDrupal()
   */
  protected static $modules = ['queue_ui_order_fixtures'];

  /**
   * Test reordering defined workers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  final public function testDefaultBulkOperation(): void {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $formUrl = Url::fromRoute('queue_ui.overview_form');
    $session = $this->assertSession();
    $this->drupalGet($formUrl);
    $this->submitForm([
      'queues[queue_order_worker_A]' => 'queue_order_worker_A',
      'queues[queue_order_worker_B]' => 'queue_order_worker_B',
      'queues[queue_order_worker_C]' => 'queue_order_worker_C',
      'queues[queue_order_worker_D]' => 'queue_order_worker_D',
      'queues[queue_order_worker_E]' => 'queue_order_worker_E',
      'queues[queue_order_worker_F]' => 'queue_order_worker_F',
    ], 'Apply to selected items');
    $session->waitForText('Processing queues');
    $session->statusMessageContainsAfterWait(
      'Items were not processed. Try to release existing items or add new items to the queues.'
    );
  }

  /**
   * Test reordering defined workers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBulkOperationsButton() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $form_url = Url::fromRoute('queue_ui.overview_form');
    $driver = $this->getSession();
    $page = $driver->getPage();
    $session = $this->assertSession();

    $this->drupalGet($form_url);

    // Show weights.
    $page->find('css', '[data-drupal-selector="tabledrag-toggle-weight"]')->press();

    // Test clicking on button without selected items.
    $session->fieldValueEquals('weight[queue_order_worker_A]', '30');
    $session->fieldValueEquals('weight[queue_order_worker_B]', '20');
    $session->fieldValueEquals('weight[queue_order_worker_C]', '10');
    $session->fieldValueEquals('weight[queue_order_worker_D]', '0');
    $session->fieldValueEquals('weight[queue_order_worker_E]', '-10');
    $session->fieldValueEquals('weight[queue_order_worker_F]', '-20');

    $this->submitForm([
      'weight[queue_order_worker_A]' => '0',
      'weight[queue_order_worker_B]' => '1',
      'weight[queue_order_worker_C]' => '2',
      'weight[queue_order_worker_D]' => '3',
      'weight[queue_order_worker_E]' => '4',
      'weight[queue_order_worker_F]' => '5',
    ], 'Save changes');
    $this->drupalGet($form_url);

    $session->fieldValueEquals('weight[queue_order_worker_A]', '0');
    $session->fieldValueEquals('weight[queue_order_worker_B]', '1');
    $session->fieldValueEquals('weight[queue_order_worker_C]', '2');
    $session->fieldValueEquals('weight[queue_order_worker_D]', '3');
    $session->fieldValueEquals('weight[queue_order_worker_E]', '4');
    $session->fieldValueEquals('weight[queue_order_worker_F]', '5');

    $this->submitForm([], 'Apply to selected items');
    $session->statusMessageExists('error');
    $session->statusMessageContains('No items selected.');
    $this->drupalGet($form_url);

    $this->submitForm([
      'operation' => 'submitBatch',
    ], 'Apply to selected items');
    $session->statusMessageExists('error');
    $session->statusMessageContains('No items selected.');
    $this->drupalGet($form_url);

    $this->submitForm([
      'operation' => 'submitRelease',
    ], 'Apply to selected items');
    $session->statusMessageExists('error');
    $session->statusMessageContains('No items selected.');
    $this->drupalGet($form_url);

    $this->submitForm([
      'operation' => 'submitClear',
    ], 'Apply to selected items');
    $session->statusMessageExists('error');
    $session->statusMessageContains('No items selected.');
    $this->drupalGet($form_url);

    $queues = [
      'queue_order_worker_A',
      'queue_order_worker_B',
      'queue_order_worker_C',
      'queue_order_worker_D',
    ];

    $queueWorkerManager = \Drupal::service('plugin.manager.queue_worker');
    $queueManager = \Drupal::service('queue');
    $count = 10;
    foreach ($queues as $queueId) {
      $queue = $queueManager->get($queueId);
      $queueTitle = $queueWorkerManager->getDefinition($queueId)['title'];

      for ($i = 1; $i <= $count; $i++) {
        $queue->createItem([]);
      }

      // Test clicking on button with selected queue and single queue item in.
      $this->submitForm([
        "queues[$queueId]" => $queueId,
        'operation' => 'submitBatch',
      ], 'Apply to selected items');

      $session->waitForElementVisible('css', '[data-drupal-messages]');

      $session->statusMessageExists('status');
      $session->statusMessageContains("Queue {$queueTitle}: 10 items successfully processed.");
      $this->drupalGet($form_url);

      $this->submitForm([
        'operation' => 'submitRelease',
      ], 'Apply to selected items');
      $session->statusMessageExists('error');
      $session->statusMessageContains('No items selected.');
      $this->drupalGet($form_url);

      $this->submitForm([
        'operation' => 'submitClear',
      ], 'Apply to selected items');
      $session->statusMessageExists('error');
      $session->statusMessageContains('No items selected.');
      $this->drupalGet($form_url);
    }
  }

  /**
   * Test the initial selection of items in the list with Queue Order enabled.
   */
  public function testInitialSelectItemsInTheList() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $form_url = Url::fromRoute('queue_ui.overview_form');
    $driver = $this->getSession();
    $page = $driver->getPage();
    $session = $this->assertSession();

    $this->drupalGet($form_url);

    // Check initial state.
    $session->checkboxNotChecked('queues[queue_order_worker_B]');
    $session->elementTextContains('css', '[data-queue-name="queue_order_worker_B"] > td:nth-child(6)', 'Cron disabled');

    // Check the queue which cron is disabled.
    $page->checkField('queues[queue_order_worker_B]');
    $session->checkboxChecked('queues[queue_order_worker_B]');

    // Click on bulk operation button to trigger default operation.
    $page->pressButton('Apply to selected items');

    // Assert the expected behavior.
    $session->statusMessageNotContainsAfterWait('No items selected');
    $session->statusMessageContainsAfterWait('Items were not processed. Try to release existing items or add new items to the queues.', 'warning');

    // Change the cron time limit to unlimited.
    $this->submitForm([], 'Save changes');

    // Check initial state.
    $session->checkboxNotChecked('queues[queue_order_worker_B]');
    $session->elementTextContains('css', '[data-queue-name="queue_order_worker_B"] > td:nth-child(6)', 'Cron disabled');

    // Check the queue which cron is disabled.
    $page->checkField('queues[queue_order_worker_B]');
    $session->checkboxChecked('queues[queue_order_worker_B]');

    // Click on bulk operation button to trigger default operation.
    $page->pressButton('Apply to selected items');

    // Assert the expected behavior.
    $session->statusMessageNotContainsAfterWait('No items selected');
    $session->statusMessageContainsAfterWait('Items were not processed. Try to release existing items or add new items to the queues.', 'warning');
  }

}

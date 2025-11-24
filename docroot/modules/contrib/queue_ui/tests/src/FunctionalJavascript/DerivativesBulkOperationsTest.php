<?php

namespace Drupal\Tests\queue_ui\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Class DerivativesBulkOperationsTest declaration.
 *
 * @package Drupal\Tests\queue_ui\FunctionalJavascript
 * @group queue_ui
 */
class DerivativesBulkOperationsTest extends WebDriverTestBase {

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
  protected static $modules = ['queue_ui_derivatives_fixtures'];

  /**
   * Test reordering defined workers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  final public function testDefaultBulkOperation(): void {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $formUrl = Url::fromRoute('queue_ui.overview_form');
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet($formUrl);

    // Make sure feature is not enabled yet.
    $session->checkboxNotChecked('features[derivatives]');

    $this->submitForm([
      'queues[channel_queue:channel_1]' => 'channel_queue:channel_1',
      'queues[channel_queue:channel_2]' => 'channel_queue:channel_2',
      'queues[channel_queue:channel_3]' => 'channel_queue:channel_3',
    ], 'Apply to selected items');
    $session->waitForText('Processing queues');
    $session->waitForElementRemoved('css', '[data-drupal-progress]');
    $session->statusMessageContainsAfterWait('Items were not processed. Try to release existing items or add new items to the queues.');

    $page->pressButton('Queue Manager Features');
    $page->checkField('features[derivatives]');
    $page->pressButton('Save changes');

    // Make sure feature is enabled.
    $session->checkboxChecked('features[derivatives]');

    $this->submitForm([
      'queues[channel_queue]' => 'channel_queue',
    ], 'Apply to selected items');
    $session->waitForText('Processing queues');
    $session->waitForElementRemoved('css', '[data-drupal-progress]');
    $session->statusMessageContainsAfterWait('Items were not processed. Try to release existing items or add new items to the queues.');
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

    $this->submitForm([], 'Save changes');
    $this->drupalGet($form_url);

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
      'channel_queue:channel_1',
      'channel_queue:channel_2',
      'channel_queue:channel_3',
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

    $page->pressButton('Queue Manager Features');
    $page->checkField('features[derivatives]');
    $page->pressButton('Save changes');

    // Make sure feature is enabled.
    $session->checkboxChecked('features[derivatives]');

    $this->submitForm([], 'Save changes');
    $this->drupalGet($form_url);

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

    $queues_derivatives = [
      'channel_queue' => [],
    ];

    // Get fresh instances of the services.
    $queueWorkerManager = \Drupal::service('plugin.manager.queue_worker');
    $queueManager = \Drupal::service('queue');
    $count = 10;

    $queues = [];
    $definitions = $queueWorkerManager->getDefinitions();
    foreach ($definitions as $queueId => $definition) {
      foreach ($queues_derivatives as $queues_derivative => &$derivative_storage) {
        if (str_starts_with($queueId, $queues_derivative . ':')) {
          $queues[] = $queueId;
          $queue = $queueManager->get($queueId);
          if (!isset($derivative_storage['title'])) {
            $derivative_storage['title'] = (string) $queueWorkerManager->getDefinition($queueId)['title'];
          }
          $derivative_storage['queue'][$queueId]['title'] = $queueWorkerManager->getDefinition($queueId)['title'];
        }
      }
    }
    unset($derivative_storage);

    foreach ($queues_derivatives as $queues_derivative => &$derivative_storage) {
      $this->generateItemsForDerivatives($derivative_storage, $count);
      // Test clicking on button with selected queue and single queue item in.
      $this->submitForm([
        "queues[$queues_derivative]" => $queues_derivative,
        'operation' => 'submitBatch',
      ], 'Apply to selected items');

      $session->waitForElementVisible('css', '[data-drupal-messages]');

      $session->statusMessageExists('status');
      $session->statusMessageContains("Queue {$derivative_storage['title']}: {$derivative_storage['total']} items successfully processed.");
      $derivative_storage['total'] = 0;
      $this->drupalGet($form_url);

      $this->generateItemsForDerivatives($derivative_storage, $count);
      $this->submitForm([
        "queues[$queues_derivative]" => $queues_derivative,
        'operation' => 'submitRelease',
      ], 'Apply to selected items');
      $session->statusMessageExists('status');
      $message = [];
      foreach (array_keys($derivative_storage['queue']) as $queueId) {
        $message[] = $count . ' lease reset in queue ' . $queueId;
      }
      $message = implode('<br/>', $message);
      $session->statusMessageContains($message);
      $derivative_storage['total'] = 0;

      $this->drupalGet($form_url);

      $this->submitForm([
        "queues[$queues_derivative]" => $queues_derivative,
        'operation' => 'submitClear',
      ], 'Apply to selected items');
      $session->pageTextContains('Are you sure you want to clear 3 queues?');
      $page->pressButton('Confirm');
      $session->statusMessageExistsAfterWait('status');
      $session->statusMessageContains('3 queues cleared');
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
    $session->checkboxNotChecked('queues[channel_queue:channel_1]');
    $session->elementTextContains('css', '[data-queue-name="channel_queue:channel_1"] > td:nth-child(6)', '59 seconds');

    // Check the queue which cron is disabled.
    $page->checkField('queues[channel_queue:channel_1]');
    $session->checkboxChecked('queues[channel_queue:channel_1]');

    // Click on bulk operation button to trigger default operation.
    $page->pressButton('Apply to selected items');

    // Assert the expected behavior.
    $session->statusMessageNotContainsAfterWait('No items selected');
    $session->statusMessageContainsAfterWait('Items were not processed. Try to release existing items or add new items to the queues.', 'warning');

    // Change the cron time limit to unlimited.
    $this->submitForm([], 'Save changes');

    // Check initial state.
    $session->checkboxNotChecked('queues[channel_queue:channel_1]');
    $session->elementTextContains('css', '[data-queue-name="channel_queue:channel_1"] > td:nth-child(6)', '59 seconds');

    // Check the queue which cron is disabled.
    $page->checkField('queues[channel_queue:channel_1]');
    $session->checkboxChecked('queues[channel_queue:channel_1]');

    // Click on bulk operation button to trigger default operation.
    $page->pressButton('Apply to selected items');

    // Assert the expected behavior.
    $session->statusMessageNotContainsAfterWait('No items selected');
    $session->statusMessageContainsAfterWait('Items were not processed. Try to release existing items or add new items to the queues.', 'warning');
  }

  /**
   * Generates items for derivative queues.
   *
   * @param array $derivative_storage
   *   A reference to an array that holds information about the derivative
   *   storage.
   * @param int $items_per_queue
   *   The number of items to generate per queue.
   */
  protected function generateItemsForDerivatives(array &$derivative_storage, int $items_per_queue): void {
    $queue_manager = \Drupal::service('queue');
    foreach (array_keys($derivative_storage['queue']) as $queueId) {
      for ($i = 1; $i <= $items_per_queue; $i++) {
        $queue_manager->get($queueId)->createItem([]);
        if (!isset($derivative_storage['total'])) {
          $derivative_storage['total'] = 0;
        }
        $derivative_storage['total']++;
      }
    }
  }

}

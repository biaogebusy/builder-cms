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
    $session->waitForElementRemoved('css', '[data-drupal-progress]');
    $this->assertJsCondition('document.querySelector("[data-drupal-messages]")');
    $session->statusMessageContains(
      'Items were not processed. Try to release existing items or add new items to the queues.'
    );
  }

}

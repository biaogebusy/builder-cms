<?php

namespace Drupal\Tests\queue_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Class PageTitleTest declaration.
 *
 * @package Drupal\Tests\queue_ui\Functional
 * @group queue_ui
 */
class PageTitleTest extends BrowserTestBase {

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
   * Test page titles.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  final public function testPageTitles(): void {
    $queue_name = 'queue_order_worker_A';
    $queue_definition = \Drupal::service('plugin.manager.queue_worker')->getDefinition($queue_name);
    $queue_title = $queue_definition['title'];
    $queue = \Drupal::queue($queue_name);
    $queue->createQueue();
    $item_id = $queue->createItem([]);
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $this->drupalGet(Url::fromRoute('queue_ui.overview_form'));
    $assertSession->titleEquals($this->prepareTitleString('Queue manager'));
    $this->clickLink('Inspect', 5);
    $assertSession->titleEquals($this->prepareTitleString("Inspecting {$queue_title} Queue"));
    $this->clickLink('View');
    $assertSession->titleEquals($this->prepareTitleString("Queue {$queue_title} Item {$item_id} Details"));
    $session->back();
    $this->clickLink('Release');
    $assertSession->titleEquals($this->prepareTitleString("Are you sure you want to release item {$item_id} of {$queue_title} queue?"));
    $this->submitForm([], 'Confirm');
    $this->clickLink('Delete');
    $assertSession->titleEquals($this->prepareTitleString("Are you sure you want to delete item {$item_id} of {$queue_title} queue?"));
    $this->submitForm([], 'Confirm');
    $assertSession->titleEquals($this->prepareTitleString("Inspecting {$queue_title} Queue"));

  }

  /**
   * Prepares a title string by appending " | Drupal" to it.
   *
   * @param string $title
   *   The title string to prepare.
   *
   * @return string
   *   The prepared title string.
   */
  private function prepareTitleString(string $title): string {
    return $title . " | Drupal";
  }

}

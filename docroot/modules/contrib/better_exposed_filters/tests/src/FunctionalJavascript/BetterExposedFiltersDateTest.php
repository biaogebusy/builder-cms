<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests the date widget for bef.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersDateTest extends BetterExposedFiltersTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $now = new \DateTime('now');
    // Create a few test nodes.
    $this->createNode([
      'title' => 'Node 1',
      'created' => $now->getTimestamp(),
      'type' => 'bef_test',
    ]);
    $oneWeek = new \DateTime('-1 week');
    $this->createNode([
      'title' => 'Node 2',
      'created' => $oneWeek->getTimestamp(),
      'type' => 'bef_test',
    ]);
    $dayBeforeYesterday = new \DateTime('-2 days');
    $this->createNode([
      'title' => 'Node 3',
      'created' => $dayBeforeYesterday->getTimestamp(),
      'type' => 'bef_test',
    ]);
  }

  /**
   * Test the offset for date field.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleDateFieldOffset(): void {
    // Test single value operator on initial load.
    $session = $this->assertSession();
    $this->drupalGet('/bef-test-date-picker');
    $date = new \DateTime('-1 day');
    $session->fieldValueEquals('created', $date->format('Y-m-d'));
    $this->assertSession()->pageTextNotContains('Node 1');
    $this->assertSession()->pageTextContains('Node 2');
    $this->assertSession()->pageTextContains('Node 3');

    // Test single value operator after form submission with a new value.
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $submittedDate = new \DateTime('-3 days');
    $this->getSession()->getPage()->fillField('created', $submittedDate->format('mdY'));
    $page->pressButton('Apply');

    $session->fieldValueEquals('created', $submittedDate->format('Y-m-d'));
    $this->assertSession()->pageTextNotContains('Node 1');
    $this->assertSession()->pageTextContains('Node 2');
    $this->assertSession()->pageTextNotContains('Node 3');
  }

  /**
   * Test the offset for date field using between.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBetweenDateFieldOffset(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('page_3')['display_options']['filters']['created']['operator'] = 'between';
    $view->storage->getDisplay('page_3')['display_options']['filters']['created']['value']['min'] = '-1 day';
    $view->storage->getDisplay('page_3')['display_options']['filters']['created']['value']['max'] = '+3 days';
    $view->save();

    // Test double value operator on initial load.
    $session = $this->assertSession();
    $this->drupalGet('/bef-test-date-picker');
    $min = new \DateTime('-1 day');
    $session->fieldValueEquals('created[min]', $min->format('Y-m-d'));
    $max = new \DateTime('+3 day');
    $session->fieldValueEquals('created[max]', $max->format('Y-m-d'));
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->pageTextNotContains('Node 2');
    $this->assertSession()->pageTextNotContains('Node 3');

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $minSubmitted = new \DateTime('-3 days');
    $this->getSession()->getPage()->fillField('created[min]', $minSubmitted->format('mdY'));
    $maxSubmitted = new \DateTime('+3 day');
    $this->getSession()->getPage()->fillField('created[max]', $maxSubmitted->format('mdY'));
    $page->pressButton('Apply');

    $session->fieldValueEquals('created[min]', $minSubmitted->format('Y-m-d'));
    $session->fieldValueEquals('created[max]', $maxSubmitted->format('Y-m-d'));
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->pageTextNotContains('Node 2');
    $this->assertSession()->pageTextContains('Node 3');
  }

}

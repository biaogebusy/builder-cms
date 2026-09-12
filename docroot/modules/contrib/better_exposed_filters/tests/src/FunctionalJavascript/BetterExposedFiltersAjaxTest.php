<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests BEF pagers.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersAjaxTest extends BetterExposedFiltersTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->turnAjaxOn();
  }

  /**
   * Tests ajax pager links.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPagerAjax(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'pager' => [
        'plugin_id' => 'bef_links',
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $this->clickLink('10');
    // Verify ajax runs not a reload.
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Tests that reset button clears URL parameters with AJAX.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testResetButtonAjax(): void {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('bef-test', ['query' => ['field_bef_boolean_value' => '1']]);

    $session->pageTextContains('Page One');
    $session->pageTextNotContains('Page Two');

    // Verify URL contains filter parameter.
    $this->assertStringContainsString('field_bef_boolean_value', $this->getSession()->getCurrentUrl());

    $page->pressButton('Reset');

    // Wait for URL to be clean (no query parameters).
    $this->assertJsCondition('window.location.search === ""');

    // Verify both nodes are present after reset.
    $session->pageTextContains('Page One');
    $session->pageTextContains('Page Two');
  }

  /**
   * Tests that bef_links triggers ajax call.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLinkAjax(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $page = $this->getSession()->getPage();

    $this->assertSession()->pageTextContains('Page one');
    $this->assertSession()->pageTextContains('Page two');

    $page->clickLink('One');
    // Verify ajax runs not a reload.
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Page one');
    $this->assertSession()->pageTextNotContains('Page two');

    // Test when a field is a multiselect.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $page = $this->getSession()->getPage();

    $this->assertSession()->pageTextContains('Page one');
    $this->assertSession()->pageTextContains('Page two');

    $page->clickLink('Aardvark');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Page one');
    $this->assertSession()->pageTextNotContains('Page two');
  }

  /**
   * Tests single selection with AJAX and autosubmit.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefLinksSingleSelect(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => TRUE,
      ],
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('/bef-test');

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // All options should be present on the page.
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextContains('Donkey');
    $session->pageTextContains('Elephant');

    // Node titles should also be present.
    $session->pageTextContains('Page One');
    $session->pageTextContains('Page Two');

    // Test selecting/unselecting multiple times.
    for ($i = 1; $i < 3; $i++) {
      // Select filter for "Aardvark".
      $page->clickLink('Aardvark');
      $this->assertSession()->assertWaitOnAjaxRequest();

      // Verify that the link got the selected class.
      $session->elementAttributeContains('css', '.bef-links a[data-bef-value="a"]', 'class', 'bef-link--selected');

      // Verify that only the "Page One" Node is present.
      $session->pageTextContains('Page One');
      $session->pageTextNotContains('Page Two');

      // Unselect filter for "Aardvark".
      $page->clickLink('Aardvark');
      $session->assertWaitOnAjaxRequest();

      // Verify that the link doesn't have the selected class.
      $session->elementAttributeNotContains('css', '.bef-links a[data-bef-value="a"]', 'class', 'bef-link--selected');

      // Verify that both nodes are now present.
      $session->pageTextContains('Page One');
      $session->pageTextContains('Page Two');
    }
  }

  /**
   * Tests multi selection with AJAX and autosubmit.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefLinksMultiSelect(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => TRUE,
      ],
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    // Enable multi select.
    $this->container->get('config.factory')->getEditable('views.view.bef_test')
      ->set('display.default.display_options.filters.field_bef_letters_value.expose.multiple', TRUE)
      ->save();

    // Visit the bef-test page.
    $this->drupalGet('/bef-test');

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // All options should be present on the page.
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextContains('Donkey');
    $session->pageTextContains('Elephant');

    // Node titles should also be present.
    $session->pageTextContains('Page One');
    $session->pageTextContains('Page Two');

    // Test selecting/unselecting multiple times.
    for ($i = 1; $i < 3; $i++) {
      // Select filter for "Aardvark".
      $page->clickLink('Aardvark');
      $this->assertSession()->assertWaitOnAjaxRequest();

      // Verify that the link got the selected class.
      $session->elementAttributeContains('css', '.bef-links a[data-bef-value="a"]', 'class', 'bef-link--selected');

      // Verify that only the "Page One" Node is present.
      $session->pageTextContains('Page One');
      $session->pageTextNotContains('Page Two');

      // Select the filter for "Bumble & the Bee".
      $page->clickLink('Bumble & the Bee');
      $session->assertWaitOnAjaxRequest();

      // Verify that both links have the selected class.
      $session->elementAttributeContains('css', '.bef-links a[data-bef-value="a"]', 'class', 'bef-link--selected');
      $session->elementAttributeContains('css', '.bef-links a[data-bef-value="b"]', 'class', 'bef-link--selected');

      // Verify that both nodes are now present.
      $session->pageTextContains('Page One');
      $session->pageTextContains('Page Two');

      // Unselect filter for "Aardvark".
      $page->clickLink('Aardvark');
      $session->assertWaitOnAjaxRequest();

      // Verify that only the "Page Two" Node is present.
      $session->pageTextContains('Page Two');
      $session->pageTextNotContains('Page One');

      // Unselect filter for "Bumble & the Bee".
      $page->clickLink('Bumble & the Bee');
      $session->assertWaitOnAjaxRequest();

      // Verify that both nodes are now present.
      $session->pageTextContains('Page One');
      $session->pageTextContains('Page Two');
    }
  }

}

<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests functionality around checkboxes.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersCheckboxTest extends BetterExposedFiltersTestBase {

  /**
   * Tests the single checkbox.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleCheckbox(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'status' => [
          'plugin_id' => 'bef_single',
        ],
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);
    $session = $this->assertSession();

    $this->drupalGet('/bef-test');
    $session->checkboxChecked('status');
    $session->pageTextContains('Page one');
    $session->pageTextNotContains('Page unpublished');

    $page = $this->getSession()->getPage();
    $page->findField('status')->uncheck();
    $page->pressButton('Apply');

    $session->checkboxNotChecked('status');
    // Both should display because treat_as_false is unchecked.
    $session->pageTextContains('Page one');
    $session->pageTextContains('Page unpublished');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'status' => [
          'plugin_id' => 'bef_single',
          'treat_as_false' => TRUE,
        ],
      ],
    ]);

    // Now test the same again.
    $this->drupalGet('/bef-test');
    $session->checkboxChecked('status');
    $session->pageTextContains('Page one');
    $session->pageTextNotContains('Page unpublished');

    $page = $this->getSession()->getPage();
    $page->findField('status')->uncheck();
    $page->pressButton('Apply');

    $session->checkboxNotChecked('status');
    // Now only the unpublished should appear.
    $session->pageTextNotContains('Page one');
    $session->pageTextContains('Page unpublished');
  }

  /**
   * Tests the checkbox functionality.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testCheckbox(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_integer_value']['expose']['multiple'] = TRUE;
    $view->storage->save();
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);
    $session = $this->assertSession();

    $this->drupalGet('/bef-test');
    $session->pageTextContains('Page one');
    $session->pageTextContains('Page with 0 value');

    $page = $this->getSession()->getPage();
    $page->findField('field_bef_integer_value[0]')->check();
    $page->pressButton('Apply');

    $session->pageTextNotContains('Page one');
    $session->pageTextContains('Page with 0 value');
  }

  /**
   * Tests the soft limit feature.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefCheckboxSoftLimit(): void {
    $view = Views::getView('bef_test');
    $session = $this->assertSession();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
          'soft_limit' => '3',
          'soft_limit_label_less' => 'Less test',
          'soft_limit_label_more' => 'More test',
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextNotContains('Donkey');
    $session->pageTextNotContains('Elephant');
    $this->clickLink('More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextContains('Donkey');
    $session->pageTextContains('Elephant');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'Less test');
    $this->clickLink('Less test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'More test');

    // The selected option is beyond the soft limit and must stay visible.
    $this->drupalGet('/bef-test', [
      'query' => [
        'field_bef_letters_value' => [
          'd' => 'd',
        ],
      ],
    ]);
    $session->checkboxChecked('field_bef_letters_value[d]');
    $session->pageTextContains('Donkey');
    $session->pageTextNotContains('Elephant');
    $this->clickLink('More test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'Less test');
    $this->clickLink('Less test');
    $session->pageTextContains('Donkey');
    $session->pageTextNotContains('Elephant');

    // Now lets test soft limit on links.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef_links',
          'soft_limit' => '3',
          'soft_limit_label_less' => 'Less test',
          'soft_limit_label_more' => 'More test',
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextNotContains('Donkey');
    $session->pageTextNotContains('Elephant');
    $this->clickLink('More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextContains('Donkey');
    $session->pageTextContains('Elephant');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'Less test');
    $this->clickLink('Less test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'More test');

    // The selected option is beyond the soft limit and must stay visible.
    $this->drupalGet('/bef-test', [
      'query' => [
        'field_bef_letters_value' => [
          'd' => 'd',
        ],
      ],
    ]);
    $selected_donkey_link = '//a[contains(@class, "bef-link--selected")]'
      . '[normalize-space() = "Donkey"]';
    $session->elementExists('xpath', $selected_donkey_link);
    $session->pageTextContains('Donkey');
    $session->pageTextNotContains('Elephant');
    $this->clickLink('More test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'Less test');
    $this->clickLink('Less test');
    $session->pageTextContains('Donkey');
    $session->pageTextNotContains('Elephant');
  }

  /**
   * Tests the scrollable container feature.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefCheckboxScrollable(): void {
    $view = Views::getView('bef_test');
    $session = $this->assertSession();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
          'scrollable' => TRUE,
          'scrollable_height' => 200,
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $session->elementExists('css', '.bef-scrollable');
    $session->elementAttributeContains('css', '.bef-scrollable', 'style', 'max-height: 200px');
  }

}

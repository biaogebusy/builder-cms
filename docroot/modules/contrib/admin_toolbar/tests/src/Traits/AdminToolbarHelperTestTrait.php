<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar\Traits;

/**
 * @file
 * Contains methods used for PHPUNIT test cases for admin toolbar modules.
 */

/**
 * Adds common methods for the test classes of the admin toolbar modules.
 *
 * Provides common functionality for any class using the trait to implement
 * Functional or FunctionJavascript test cases:
 *   - Assert a link in the admin menu exists or not.
 *
 * @see \Drupal\Tests\admin_toolbar\Functional\AdminToolbarToolsSortTest
 */
trait AdminToolbarHelperTestTrait {

  /**
   * The HTML IDs used to test the display of the admin toolbar.
   *
   * @var array<string>
   */
  protected $testAdminToolbarHtmlIds = [
    'admin_tray' => 'toolbar-item-administration-tray',
  ];

  /**
   * The default CSS class used for testing links in the admin toolbar.
   *
   * Gives the ability to tests to specify a default CSS class to be used for
   * asserting the existence of links in the admin toolbar.
   *
   * @var string
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksCustomTest::setUp()
   * @see \Drupal\Tests\admin_toolbar_tools\Traits\AdminToolbarToolsEntityCreationTrait::setUp()
   */
  protected $testAdminToolbarDefaultLinkCssClass = 'toolbar-icon toolbar-icon-';

  /**
   * Checks that a specific link exists in the admin toolbar.
   *
   * @param string $link_url
   *   The url of the link to assert it exists in the admin menu.
   * @param string $link_text
   *   The optional text in the link to assert it exists in the admin menu.
   * @param int $link_position
   *   The optional position of the link in the menu to assert it exists in the
   *   admin menu. The position is 1-based, so the first link is in position 1.
   *   If not provided or 0, the position is not checked.
   * @param string $link_css_class
   *   The optional CSS class in the link to assert it exists in the admin menu.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksCustomTest::testAdminToolbarToolsExtraLinksCustom()
   */
  protected function assertAdminToolbarMenuLinkExists(string $link_url = '', string $link_text = '', int $link_position = 0, string $link_css_class = '') {
    // Build a CSS selector with the provided conditions.
    $link_css_conditions = '';

    // If a link text is provided, use case sensitive ':contains()' operator.
    if (!empty($link_text)) {
      $link_css_conditions .= ':contains("' . $link_text . '")';
    }
    if (!empty($link_url)) {
      // A CSS selector has to be used here, since xpath 1.0 does not support
      // 'ends-with'. Use the ends with CSS selector '$=' to support absolute
      // URLs while still ensuring the expected URL is an exact match.
      $link_css_conditions .= '[href$="' . $link_url . '"]';
    }
    // If a class is provided, check the classes of the link contain it ('*=').
    if (!empty($link_css_class)) {
      $link_css_conditions .= '[class*="' . $link_css_class . '"]';
    }
    // Allow test classes using the trait to set a default CSS class to be used.
    elseif (!empty($this->testAdminToolbarDefaultLinkCssClass)) {
      // If no specific CSS class is provided, use the default one.
      $link_css_conditions .= '[class*="' . $this->testAdminToolbarDefaultLinkCssClass . '"]';
    }

    // If a position is provided, add a ':nth-child()' selector to the CSS
    // expression.
    $link_position_conditions = '';
    if ($link_position > 0) {
      $link_position_conditions = ':nth-child(' . $link_position . ')';
    }

    // Assert whether the link selected with the CSS expression exists in the
    // admin toolbar.
    $this->assertSession()
      ->elementExists('css', 'div[id="' . $this->testAdminToolbarHtmlIds['admin_tray'] . '"] li' . $link_position_conditions . '[class*="menu-item"] > a' . $link_css_conditions);
  }

  /**
   * Checks that a specific link does not exist in the admin toolbar.
   *
   * @param string $link_url
   *   The url to assert it does not exist in the admin menu.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertAdminToolbarMenuLinkNotExists(string $link_url) {
    // A simple xpath expression is enough here, since it should be less
    // restrictive, in terms of conditions.
    $this->assertSession()
      ->elementNotExists('xpath', '//div[@id="' . $this->testAdminToolbarHtmlIds['admin_tray'] . '"]//a[contains(@href, "' . $link_url . '")]');
  }

}

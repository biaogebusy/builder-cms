<?php

namespace Drupal\Tests\module_filter\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\DocumentElement;

/**
 * Base class for Module Filter javascript tests.
 */
abstract class ModuleFilterJavascriptTestBase extends WebDriverTestBase {

  /**
   * The standard modules to load for all browser tests.
   *
   * Additional modules can be specified in the tests that need them.
   *
   * @var array
   */
  protected static $modules = ['module_filter', 'red', 'yellow', 'blue'];

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * The default theme.
   *
   * Javascript tests need 'claro' theme not 'stark'.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * A user with administration rights.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an administrator user with the required permissions.
    // 'administer modules' is needed for admin/modules
    // 'administer permissions' is needed for admin/people/permissions
    // 'administer site configuration' is needed for admin/reports/updates.
    $this->adminUser = $this->drupalCreateUser([
      'administer modules',
      'administer permissions',
      'administer site configuration',
    ]);
    $this->adminUser->set('name', 'Minnie the Admin')->save();
  }

  /**
   * Looks for the specified text and returns TRUE when it is unavailable.
   *
   * Core JSWebAssert has a function waitForText() but there is no equivalent to
   * wait until text is hidden, as there is for some other page elements.
   * Therefore define that function here, based on waitForText() in
   * core/tests/Drupal/FunctionalJavascriptTests/JSWebAssert.php.
   *
   * @param string $text
   *   The text to wait for.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return bool
   *   TRUE if not found, FALSE if found.
   */
  public function waitForNoText($text, $timeout = 10000) {
    $page = $this->getSession()->getPage();
    return (bool) $page->waitFor($timeout / 1000, function (DocumentElement $page) use ($text) {
      $actual = preg_replace('/\\s+/u', ' ', $page->getText());
      // Negative look-ahead on the text that should be hidden.
      $regex = '/^((?!' . preg_quote($text, '/') . ').)*$/ui';
      return (bool) preg_match($regex, $actual);
    });
  }

}

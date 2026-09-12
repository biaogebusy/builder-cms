<?php

declare(strict_types=1);

namespace Drupal\Tests\autologout\FunctionalJavascript;

use Drupal\Core\Config\ConfigFactory;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests that the autologout cookies are set and read correctly.
 *
 * @group Autologout
 */
class AutologoutCookieTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'autologout',
  ];

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected ConfigFactory $configFactory;

  /**
   * User to test autologout.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $privilegedUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a privileged user for testing.
    $this->privilegedUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'administer autologout',
      'change own logout threshold',
      'view the administration theme',
    ]);

    $this->configFactory = \Drupal::service('config.factory');
    $this->configFactory->getEditable('autologout.settings')
      ->set('timeout', 2)
      ->set('padding', 5)
      ->save();
    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Tests that the dialog extends the cookie accordingly.
   */
  public function testDialogCookie(): void {
    $assert_session = $this->assertSession();

    // Navigate to a page to start the autologout timer cleanly.
    $this->drupalGet('user');

    // Check that the initial cookie exists.
    $cookie = $this->getSession()->getCookie('Drupal.visitor.autologout_login');
    $this->assertGreaterThanOrEqual(0, $cookie);

    // Wait for the timeout dialog to appear, click "Yes" to extend the session.
    $dialog_locator = 'div[aria-describedby=autologout-confirm]';
    $dialog = $assert_session->waitForElement('css', $dialog_locator, 3000);
    $assert_session->buttonExists('Yes', $dialog)->click();
    $assert_session->waitForElementRemoved('css', $dialog_locator);

    // Check that the cookie was updated.
    $cookie_updated = $this->getSession()->getCookie('Drupal.visitor.autologout_login');
    $this->assertGreaterThan($cookie, $cookie_updated, 'Cookie was not updated after clicking "Yes" in dialog.');
  }

}

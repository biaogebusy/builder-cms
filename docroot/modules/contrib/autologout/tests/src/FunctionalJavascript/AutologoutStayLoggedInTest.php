<?php

namespace Drupal\Tests\autologout\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test that user can remain logged in by confirming they are still active.
 */
#[Group('autologout')]
#[RunTestsInSeparateProcesses]
class AutologoutStayLoggedInTest extends WebDriverTestBase {

  const MODAL_SELECTOR = 'div[aria-describedby=autologout-confirm]';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['autologout'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User for the test.
   *
   * @var \Drupal\user\Entity\User
   */
  protected UserInterface $authenticatedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->authenticatedUser = $this->drupalCreateUser();
    $moduleConfig = $this->container->get('config.factory')->getEditable('autologout.settings');
    $moduleConfig->set('timeout', 3)->set('padding', 2)->save();

    $this->drupalLogin($this->authenticatedUser);
  }

  /**
   * Check that the user remains logged in after confirming multiple times.
   */
  public function testStayLoggedInMultipleTimes(): void {
    // The current active reset timer is hard coded to 30 seconds - which means
    // we must wait a total of a minute to be able to test the confirm button
    // multiple times.
    $this->markTestSkipped('Test is unreasonably slow due to the need to wait for a 30 second activity reset timer.');

    $session = $this->assertSession();
    $this->drupalGet('node');

    // The user confirms they want to stay logged in a number of times.
    for ($i = 0; $i < 3; $i++) {
      // The timeout currently accounts for the 30-second activity reset timer.
      $dialog = $session->waitForElement('css', self::MODAL_SELECTOR, 35000);
      $session->buttonExists('Yes', $dialog)->click();
      $session->waitForElementRemoved('css', self::MODAL_SELECTOR);

      // Check we are still logged in.
      self::assertTrue($this->drupalUserIsLoggedIn($this->authenticatedUser));
    }
  }

}

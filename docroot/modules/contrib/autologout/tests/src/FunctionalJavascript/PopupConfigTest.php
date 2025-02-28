<?php

namespace Drupal\Tests\autologout\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the popup configuration.
 *
 * @description Ensure that the settings for the popup work as expected.
 *
 * @group Autologout
 */
class PopupConfigTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'autologout',
  ];

  /**
   * User with admin rights.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $privilegedUser;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $moduleConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->privilegedUser = $this->drupalCreateUser();
    $this->moduleConfig = $this->container->get('config.factory')->getEditable('autologout.settings');

    // For testing purposes set the timeout to 2 seconds.
    $this->moduleConfig->set('timeout', 2)->set('padding', 3)->save();

    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Tests that configurable text is displayed in the popup.
   */
  public function testConfigurableText() {
    // Set custom values for the popup.
    $title = 'Custom title';
    $message = 'Custom message';
    $yes_btn = 'Yes button';
    $no_btn = 'No button';
    $popup_selector = 'div[aria-describedby=autologout-confirm]';

    $this->moduleConfig->set('dialog_title', $title)
      ->set('message', $message)
      ->set('yes_button', $yes_btn)
      ->set('no_button', $no_btn)
      ->save();

    $this->drupalGet('user');

    $session = $this->assertSession();
    // Wait for popup to trigger.
    $session->waitForElement('css', $popup_selector, 2500);
    // Check that popup gets displayed.
    $session->elementExists('css', $popup_selector);
    // Check for custom values.
    $session->pageTextContains($title);
    $session->pageTextContains($message);
    $session->pageTextContains($yes_btn);
    $session->pageTextContains($no_btn);
  }

  /**
   * Tests that disable buttons settings option works as expected.
   */
  public function testDisableButtons() {
    $popup_selector = 'div[aria-describedby=autologout-confirm]';
    $buttons_selector = 'div[aria-describedby=autologout-confirm] > div.ui-dialog-buttonpane';

    $this->drupalGet('user');

    $session = $this->assertSession();
    // Wait for popup.
    $session->waitForElement('css', $popup_selector, 2500);
    // Check that popup with buttons gets displayed.
    $session->elementExists('css', $popup_selector);
    $session->elementExists('css', $buttons_selector);

    // Disable buttons.
    $this->moduleConfig->set('disable_buttons', TRUE)->save();

    $this->drupalGet('user');

    // Wait for popup.
    $session->waitForElement('css', $popup_selector);
    // Check that popup without buttons gets displayed.
    $session->elementExists('css', $popup_selector);
    $session->elementNotExists('css', $buttons_selector);
  }

}

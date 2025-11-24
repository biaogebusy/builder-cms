<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests for the Lorem Ipsum module.
 *
 * @group private_message
 */
class PrivateMessageSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */

  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['private_message'];

  /**
   * The admin user used for the test.
   */
  private UserInterface $adminUser;

  /**
   * The User used for the test.
   */
  private UserInterface $user;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->user = $this->createUser();
    $this->adminUser = $this->DrupalCreateUser([
      'administer site configuration',
      'administer private message module',
    ]);
  }

  /**
   * Tests that the settings page can be reached.
   */
  public function testSettingsPageExists(): void {
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/private-message/config');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/private-message/config');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the config form.
   */
  public function testConfigForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/private-message/config');
    $this->assertSession()->statusCodeEquals(200);

    // Test form submission.
    $this->submitForm([], t('Save configuration'));
    $this->assertSession()
      ->pageTextContainsOnce('The configuration options have been saved.');
  }

}

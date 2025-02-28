<?php

namespace Drupal\Tests\swiftmailer\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Transport and Message Settings UI.
 *
 * @group swiftmailer
 */
class SwiftMailerSettingsTest extends WebDriverTestBase {

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
    'swiftmailer',
    'mailsystem',
    'block',
  ];

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface.
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');

    $this->adminUser = $this->createUser(['administer swiftmailer']);
  }

  /**
   * Tests the Transport Settings.
   */
  public function testTransportSettings() {
    // Unauthorized user should not have access.
    $this->drupalGet('admin/config/swiftmailer/transport');
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');

    // Login..
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/swiftmailer/transport');
    $this->assertSession()->pageTextContains('Transport types');

    $session = $this->getSession();
    $page = $session->getPage();

    // Select Smtp tranport option.
    $this->assertSession()->fieldExists('transport[type]')->selectOption('smtp');
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-transport-configuration-smtp');
    $page->fillField('transport[configuration][smtp][credential_provider]', 'swiftmailer');
    $page->fillField('transport[configuration][smtp][credentials][swiftmailer][username]', 'example');
    $page->fillField('transport[configuration][smtp][credentials][swiftmailer][password]', 'pass');
    $this->submitForm([], 'Save configuration');

    $this->assertSession()->pageTextContains('using the SMTP transport type.');

    // Loading configuration to check if is set up correctly.
    $config = $this->config('swiftmailer.transport');
    $transport = $config->get('transport');
    $provider = $config->get('smtp_credential_provider');
    $user = $config->get('smtp_credentials.swiftmailer.username');
    $password = $config->get('smtp_credentials.swiftmailer.password');
    $this->assertSame('smtp', $transport);
    $this->assertSame('swiftmailer', $provider);
    $this->assertSame('example', $user);
    $this->assertSame('pass', $password);

    // Select Spool tranport option.
    $this->assertSession()->fieldExists('transport[type]')->selectOption('spool');
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-transport-configuration-spool');
    $page->fillField('transport[configuration][spool][directory]', 'aaaaa');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('using the Spool transport type.');

    // Loading configuration to check if is set up correctly.
    $config = $this->config('swiftmailer.transport');
    $transport = $config->get('transport');
    $directory = $config->get('spool_directory');
    $this->assertSame('spool', $transport);
    $this->assertSame('aaaaa', $directory);

    // Select Sendmail tranport option.
    $this->assertSession()->fieldExists('transport[type]')->selectOption('sendmail');
    $this->assertSession()->waitForElementVisible('css', '.js-form-item-transport-configuration-sendmail');
    $page->fillField('transport[configuration][sendmail][path]', 'bbbbb');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('using the Sendmail transport type.');

    // Loading configuration to check if is set up correctly.
    $config = $this->config('swiftmailer.transport');
    $transport = $config->get('transport');
    $path = $config->get('sendmail_path');
    $this->assertSame('sendmail', $transport);
    $this->assertSame('bbbbb', $path);
  }

  /**
   * Tests the Message Settings.
   */
  public function testMessageSettings() {
    $this->drupalGet('admin/config/swiftmailer/transport');
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');

    // Login..
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/swiftmailer/transport');
    $this->assertSession()->pageTextContains('Transport types');

    $this->clickLink('Messages');
    $this->assertSession()->pageTextContains('Content type');

    $this->submitForm([
      'content_type[type]' => 'text/html',
      'generate_plain[mode]' => TRUE,
      'character_set[type]' => 'EUC-CN',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('swiftmailer.message');
    $content_type = $config->get('content_type');
    $mode = $config->get('generate_plain');
    $character = $config->get('character_set');
    $this->assertSame('text/html', $content_type);
    $this->assertEquals(TRUE, $mode);
    $this->assertSame('EUC-CN', $character);
  }

}

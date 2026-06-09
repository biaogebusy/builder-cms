<?php

namespace Drupal\Tests\ai_api_explorer\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the provider setup list controller.
 *
 * @group ai
 */
class ExplorerSetupListTest extends BrowserTestBase {

  /**
   * The modules to enable for this test.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'ai',
    'ai_api_explorer',
  ];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create container.
    $this->container->get('kernel')->rebuildContainer();
    \Drupal::setContainer($this->container);
  }

  /**
   * Test the API Explorer without access.
   */
  public function testApiExplorerPageNoAccess() {
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/explorers');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test the API Explorer page without any providers.
   */
  public function testApiExplorerPageEmptyAccess() {
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
      'access ai prompt',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/explorers');
    $this->assertSession()->statusCodeEquals(200);

    // Should not have any API Explorers.
    $this->assertSession()->pageTextContains('No API Explorer is configured because you are missing providers');
    $this->assertSession()->pageTextNotContains('Chat Generator');
  }

  /**
   * Test to install the AI Test module and check the API Explorer.
   */
  public function testApiExplorerPageWithProviders() {
    // Enable ai_api_explorer and ai_test modules.
    $this->container->get('module_installer')->install(['ai_test']);
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
      'access ai prompt',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/explorers');
    $this->assertSession()->statusCodeEquals(200);

    // Chat Generator should be available.
    $this->assertSession()->pageTextContains('Chat Generator');
    $this->assertSession()->pageTextNotContains('No API Explorer is configured because you are missing providers');
  }

}

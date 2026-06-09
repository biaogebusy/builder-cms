<?php

namespace Drupal\Tests\ai\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the provider setup list controller.
 *
 * @group ai
 */
class ProviderSetupListTest extends BrowserTestBase {

  /**
   * The modules to enable for this test.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'ai',
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
   * Tests that the listing page is not allowed.
   */
  public function testProviderListingPageEmptyNoAccess() {
    $account = $this->drupalCreateUser([
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/providers');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the listing page show a message when allowed without provider.
   */
  public function testProviderListingPageEmptyAccess() {
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/providers');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->pageTextContains('No AI provider is configured.');
    $this->assertSession()->pageTextNotContains('AI Test');
  }

  /**
   * Tests that the listing page show a message when allowed with provider.
   */
  public function testProviderListingPageProviderAccess() {
    // Install AI Test module.
    $this->container->get('module_installer')->install(['ai_test']);
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/providers');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is not an empty reaction rule listing.
    $this->assertSession()->pageTextContains('AI Test');
    $this->assertSession()->pageTextNotContains('No AI provider is configured.');
  }

  /**
   * Tests that the vdb page is not allowed.
   */
  public function testProviderVdbListingPageEmptyNoAccess() {
    $account = $this->drupalCreateUser([
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/vdb_providers');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the vdb page show a message when allowed without provider.
   */
  public function testProviderVdbListingPageEmptyAccess() {
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/vdb_providers');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->pageTextContains('No vector database provider is configured.');
  }

  /**
   * Tests that the vdb page show a message when allowed with provider.
   */
  public function testProviderVdbListingPageProviderAccess() {
    // Install AI Test module.
    $this->container->get('module_installer')->install(['ai_search', 'search_api', 'test_ai_vdb_provider_mysql']);
    $account = $this->drupalCreateUser([
      'administer ai providers',
      'administer ai',
      'administer site configuration',
      'access content',
      'view the administration theme',
      'access administration pages',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/vdb_providers');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is not an empty reaction rule listing.
    $this->assertSession()->pageTextNotContains('No vector database provider is configured.');
    $this->assertSession()->pageTextContains('Test MySQL VDB Provider');
  }

}

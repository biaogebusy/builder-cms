<?php

namespace Drupal\Tests\pathauto\Functional;

use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests cache invalidation after bulk alias operations.
 *
 * @group pathauto
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class PathautoCacheInvalidationTest extends BrowserTestBase {

  use PathautoTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'pathauto', 'page_cache', 'dynamic_page_cache'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The pathauto pattern.
   *
   * @var \Drupal\pathauto\PathautoPatternInterface
   */
  protected $pattern;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $this->adminUser = $this->drupalCreateUser([
      'administer pathauto',
      'administer url aliases',
      'bulk update aliases',
      'bulk delete aliases',
      'create url aliases',
      'access content',
    ]);
    $this->drupalLogin($this->adminUser);

    // Allow existing aliases to be updated.
    $this->config('pathauto.settings')
      ->set('update_action', PathautoGeneratorInterface::UPDATE_ACTION_DELETE)
      ->save();

    $this->pattern = $this->createPattern('node', '/content/[node:title]');
  }

  /**
   * Primes the page cache for an entity and asserts it works.
   *
   * @param string $path
   *   The path to visit.
   */
  protected function primeCacheAndAssert($path) {
    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');

    $this->drupalGet($path);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');
  }

  /**
   * Tests that bulk update invalidates entity caches.
   */
  public function testBulkUpdateInvalidatesCache() {
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test node']);

    // Prime the page cache.
    $path = 'node/' . $node->id();
    $this->primeCacheAndAssert($path);

    // Change the pattern so bulk update actually regenerates aliases.
    $this->pattern->delete();
    $this->createPattern('node', '/archive/[node:title]');

    // Log back in and run bulk update with "all" action.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'update[canonical_entities:node]' => TRUE,
      'action' => 'all',
    ];
    $this->drupalGet('admin/config/search/path/update_bulk');
    $this->submitForm($edit, 'Update');
    $this->assertSession()->pageTextContains('Generated 1 URL alias.');

    // Visit node as anonymous again — cache should be invalidated.
    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

  /**
   * Tests that batch delete (keep custom aliases) invalidates entity caches.
   */
  public function testBatchDeleteInvalidatesCache() {
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test node']);
    $this->assertEntityAliasExists($node);

    // Prime the page cache.
    $path = 'node/' . $node->id();
    $this->primeCacheAndAssert($path);

    // Log back in and run bulk delete with keep_custom_aliases enabled
    // so it goes through batchDelete() which has cache invalidation.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'delete[all_aliases]' => TRUE,
      'options[keep_custom_aliases]' => TRUE,
    ];
    $this->drupalGet('admin/config/search/path/delete_bulk');
    $this->submitForm($edit, 'Delete aliases now!');

    // Visit node as anonymous again — cache should be invalidated.
    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

  /**
   * Tests that delete all aliases (fast path) invalidates entity caches.
   */
  public function testDeleteAllInvalidatesCache() {
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test node']);
    $this->assertEntityAliasExists($node);

    // Prime the page cache.
    $path = 'node/' . $node->id();
    $this->primeCacheAndAssert($path);

    // Log back in and delete all aliases without keeping custom ones
    // (fast path that bypasses batch).
    $this->drupalLogin($this->adminUser);
    $edit = [
      'delete[all_aliases]' => TRUE,
      'options[keep_custom_aliases]' => FALSE,
    ];
    $this->drupalGet('admin/config/search/path/delete_bulk');
    $this->submitForm($edit, 'Delete aliases now!');

    // Visit node as anonymous again — cache should be invalidated.
    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

  /**
   * Tests that per-type delete (fast path) invalidates entity caches.
   */
  public function testDeleteByTypeInvalidatesCache() {
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Test node']);
    $this->assertEntityAliasExists($node);

    // Prime the page cache.
    $path = 'node/' . $node->id();
    $this->primeCacheAndAssert($path);

    // Log back in and delete only node aliases without keeping custom ones.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'delete[plugins][canonical_entities:node]' => TRUE,
      'options[keep_custom_aliases]' => FALSE,
    ];
    $this->drupalGet('admin/config/search/path/delete_bulk');
    $this->submitForm($edit, 'Delete aliases now!');

    // Visit node as anonymous again — cache should be invalidated.
    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
  }

}

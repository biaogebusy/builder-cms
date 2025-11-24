<?php

namespace Drupal\Tests\admin_toolbar\Functional;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Tests\toolbar\Functional\ToolbarAdminMenuTest;

/**
 * Tests the caching of the admin menu subtree items.
 *
 * @group admin_toolbar
 */
class AdminToolbarAdminMenuTest extends ToolbarAdminMenuTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar',
  ];

  /**
   * Tests that the 'toolbar/subtrees/{hash}' is reachable and correct.
   *
   * This is a workaround for a failing test in core 10.2:
   *   'X-Requested-With: XMLHttpRequest'
   * Remove after dropping support for Drupal 10.2 and below.
   */
  public function testSubtreesJsonRequest(): void {
    // Only alter this test on Drupal 10.0 through 10.2.
    if (version_compare(\Drupal::VERSION, '10.0.0', '<') || version_compare(\Drupal::VERSION, '10.3.0', '>=')) {
      parent::testSubtreesJsonRequest();
      return;
    }

    $admin_user = $this->adminUser;
    $this->drupalLogin($admin_user);
    // Request a new page to refresh the drupalSettings object.
    $subtrees_hash = $this->getSubtreesHash();

    $this->drupalGet('toolbar/subtrees/' . $subtrees_hash, ['query' => [MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_ajax']], ['X-Requested-With' => 'XMLHttpRequest']);
    $ajax_result = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('setToolbarSubtrees', $ajax_result[0]['command'], 'Subtrees response uses the correct command.');
    $this->assertEquals([
      'system-admin_content',
      'system-admin_structure',
      'system-themes_page',
      'system-modules_list',
      'system-admin_config',
      'entity-user-collection',
      'front',
    ], array_keys($ajax_result[0]['subtrees']), 'Correct subtrees returned.');
  }

  /**
   * Get the hash value from the admin menu subtrees route path.
   *
   * @return string
   *   The hash value from the admin menu subtrees route path.
   */
  private function getSubtreesHash() {
    $settings = $this->getDrupalSettings();
    // The toolbar module defines a route '/toolbar/subtrees/{hash}' that
    // returns JSON for the rendered subtrees. This hash is provided to the
    // client in drupalSettings.
    return $settings['toolbar']['subtreesHash'];
  }

}

<?php

namespace Drupal\Tests\views_custom_cache_tag\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;

/**
 * Tests time-based invalidation for the custom_tag views cache plugin.
 *
 * @group views_custom_cache_tag
 */
final class CustomCacheTagsTimeInvalidationTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'node',
    'views',
    'path',
    'views_custom_cache_tag_demo',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    // Disable page_cache as that does not respect cache age and interferes with
    // testing, see https://www.drupal.org/node/2352009.
    \Drupal::service('module_installer')->uninstall(['page_cache']);
  }

  /**
   * Updates a view display to use the custom_tag cache plugin and lifespan.
   */
  private function setViewCacheOptions(string $view_id, string $display_id, string $lifespan, string $expr = '', string $tag = ''): void {
    /** @var \Drupal\views\Entity\View $view */
    $view = View::load($view_id);
    $display = $view->getDisplay($display_id);
    $cache['type'] = 'custom_tag';
    if ($lifespan) {
      $cache['options']['custom_tag_output_lifespan'] = $lifespan;
    }
    if ($expr) {
      $cache['options']['custom_tag_output_lifespan_expression'] = $expr;
    }
    if ($tag) {
      $cache['options']['custom_tag'] = $tag;
    }

    $display['display_options']['cache'] = $cache;

    $displays = $view->get('display');
    $displays[$display_id] = $display;
    $view->set('display', $displays);
    $view->save();
  }

  /**
   * Test with a fixed TTL.
   */
  public function testFixedTtlExpiresViewsCacheOutput(): void {
    $title1 = 'Initial Content';
    $node1 = Node::create([
      'type' => 'node_type_a',
      'title' => $title1 ,
      'created' => 1,
    ]);
    $node1->save();

    $this->setViewCacheOptions('view_node_type_ab', 'page_2', '2');
    $view_url = Url::fromRoute('view.view_node_type_ab.page_2', ['arg_0' => 'node_type_a']);
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');
    $this->assertSession()->pageTextContains($title1);

    // Due to the invalid cache tag, we prevent that the view is invalidated
    // through the cache tag and is therefore still valid until the given time.
    $title2 = 'Later Content';
    $node2 = Node::create([
      'type' => 'node_type_a',
      'title' => $title2,
      'created' => 2,
    ]);
    $node2->save();
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');
    $this->assertSession()->pageTextContains($title1);
    $this->assertSession()->pageTextNotContains($title2);

    // After expiry, view output should invalidate and include the new node.
    sleep(5);
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');
    $this->assertSession()->pageTextContains($title2);
  }

  /**
   * Test with a strtotime() computed dynamic TTL.
   */
  public function testStrtotimeExpressionExpiresViewsCacheOutput(): void {
    $title1 = 'Initial Content';
    $node1 = Node::create([
      'type' => 'node_type_a',
      'title' => $title1 ,
      'created' => 1,
    ]);
    $node1->save();

    $this->setViewCacheOptions('view_node_type_ab', 'page_2', 'custom', '+3 seconds');
    $view_url = Url::fromRoute('view.view_node_type_ab.page_2', ['arg_0' => 'node_type_a'])->setAbsolute();
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');
    $this->assertSession()->pageTextContains($title1);

    // Due to the invalid cache tag, we prevent that the view is invalidated
    // through the cache tag and is therefore still valid until the given time.
    $title2 = 'Later Content';
    $node2 = Node::create([
      'type' => 'node_type_a',
      'title' => $title2,
      'created' => 2,
    ]);
    $node2->save();
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');
    $this->assertSession()->pageTextContains($title1);
    $this->assertSession()->pageTextNotContains($title2);

    // After expiry, view output should invalidate and include the new node.
    sleep(6);
    $this->drupalGet($view_url);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');
    $this->assertSession()->pageTextContains($title2);
  }

}

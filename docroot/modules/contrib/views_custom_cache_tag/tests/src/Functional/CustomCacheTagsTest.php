<?php

namespace Drupal\Tests\views_custom_cache_tag\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\views\Entity\View;

/**
 * Tests the custom cache tags in views.
 *
 * @group views_custom_cache_tag
 */
class CustomCacheTagsTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait {
    assertPageCacheContextsAndTags as protected assertPageCacheContextsAndTagsOriginal;
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array(
    'node',
    'views',
    'menu_ui',
    'path',
    'views_custom_cache_tag_demo'
  );

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

 /**
   * Asserts page cache miss, then hit for the given URL; checks cache headers.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to test.
   * @param string[] $expected_contexts
   *   The expected cache contexts for the given URL.
   * @param string[] $expected_tags
   *   The expected cache tags for the given URL.
   */
  protected function assertPageCacheContextsAndTags(Url $url, array $expected_contexts, array $expected_tags) {
    $query = $url->getOption('query');
    if (empty($query['_format'])) {
      $this->assertPageCacheContextsAndTagsOriginal($url, $expected_contexts, $expected_tags);
    }
    else {
      $absolute_url = $url->setAbsolute()->toString();
      sort($expected_contexts);
      sort($expected_tags);

      // Assert cache miss + expected cache contexts + tags.
      $this->drupalGet($absolute_url);
      $this->assertEquals('MISS', $this->getSession()->getResponseHeader('X-Drupal-Cache'));
      $this->assertCacheTags($expected_tags);
      $this->assertCacheContexts($expected_contexts);

      // Assert cache hit + expected cache contexts + tags.
      $this->drupalGet($absolute_url);
      $this->assertCacheTags($expected_tags);
      $this->assertCacheContexts($expected_contexts);

      // Assert page cache item + expected cache tags.
      $cid_parts = [$url->setAbsolute()->toString(), $query['_format']];
      $cid = implode(':', $cid_parts);
      $cache_entry = \Drupal::cache('page')->get($cid);
      sort($cache_entry->tags);
      $this->assertEquals($expected_tags, $cache_entry->tags);
    }
  }

  /**
   * Tests the cache tag invalidation.
   */
  public function testCustomCacheTags() {

    $this->enablePageCaching();
    $cache_contexts = array('theme', 'timezone', 'languages:language_content', 'languages:language_interface', 'url', 'user.node_grants:view', 'user.permissions');
    $cache_contexts_rest = array('theme', 'request_format', 'languages:language_content', 'languages:language_interface', 'url', 'user.node_grants:view', 'user.permissions');
    // Create a new node of type A.
    $node_a = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'node_type_a',
      'created' => 1,
      'title' => $this->randomMachineName(8),
      'nid' => 2,
            ]);
    $node_a->enforceIsNew(TRUE);
    $node_a->save();

    // Create a new node of type B.
    $node_b = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'node_type_b',
      'created' => 1,
      'title' => $this->randomMachineName(8),
      'nid' => 3,
    ]);
    $node_b->enforceIsNew(TRUE);
    $node_b->save();

    // Check the cache tags in the views.
    $this->assertPageCacheContextsAndTags(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_a')), $cache_contexts, array(
      'config:filter.format.plain_text',
      'config:views.view.view_node_type_ab',
      'config:user.role.anonymous',
      'http_response',
      'node:2',
      'node:type:node_type_a',
      'node_view',
      'rendered',
      'user:0',
      'user_view',
    ));
    $this->assertPageCacheContextsAndTags(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_b')), $cache_contexts, array(
      'config:filter.format.plain_text',
      'config:views.view.view_node_type_ab',
      'config:user.role.anonymous',
      'http_response',
      'node:3',
      'node:type:node_type_b',
      'node_view',
      'rendered',
      'user:0',
      'user_view',
    ));

    // Check the cache tags in the views.
    $this->assertPageCacheContextsAndTags(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_a'), array('query' => array('_format' => 'json'))), $cache_contexts_rest, array(
      'config:views.view.view_node_type_ab_rest',
      'config:user.role.anonymous',
      'http_response',
      'node:2',
      'node:type:node_type_a',
    ));
    $this->assertPageCacheContextsAndTags(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_b'), array('query' => array('_format' => 'json'))), $cache_contexts_rest, array(
      'config:views.view.view_node_type_ab_rest',
      'config:user.role.anonymous',
      'http_response',
      'node:3',
      'node:type:node_type_b',
    ));

    // Create a new node of type B ensure that the page
    // cache entry invalidates.
    $node_b = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'node_type_b',
      'created' => 1,
      'title' => $title = $this->randomMachineName(8),
      'nid' => 4,
    ]);
    $node_b->enforceIsNew(TRUE);
    $node_b->save();
    // Make sure the node type A tags are not invalidated.
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_a')), 'HIT');
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_a'), array('query' => array('_format' => 'json'))), 'HIT');
    // Ensure cache tags invalidation in node type B view.
    $this->assertPageCacheContextsAndTags(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_b')), $cache_contexts, array(
      'config:filter.format.plain_text',
      'config:views.view.view_node_type_ab',
      'config:user.role.anonymous',
      'http_response',
      'node:3',
      'node:4',
      'node:type:node_type_b',
      'node_view',
      'rendered',
      'user:0',
      'user_view'
    ));

    $this->assertPageCacheContextsAndTags(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_b'), array('query' => array('_format' => 'json'))), $cache_contexts_rest, array(
      'config:views.view.view_node_type_ab_rest',
      'config:user.role.anonymous',
      'http_response',
      'node:3',
      'node:4',
      'node:type:node_type_b',
    ));


    $this->drupalGet(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_b')));
    $this->assertSession()->pageTextContains($title);

    $this->drupalGet(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_b'), array('query' => array('_format' => 'json'))));
    $this->assertSession()->responseContains($title);

    // Save the view again, check the cache tag invalidation.
    $view_b = View::load('view_node_type_ab');
    $view_b->save();

    // Ensure cache tags invalidation in node type A & B views.
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_b')), 'MISS');
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_ab.page_1', array('arg_0' => 'node_type_a')), 'MISS');

    // Save the view again, check the cache tag invalidation.
    $view_b = View::load('view_node_type_ab_rest');
    $view_b->save();

    // Ensure cache tags invalidation in node type A & B views.
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_b'), array('query' => array('_format' => 'json'))), 'MISS');
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_ab_rest.rest_export_1', array('arg_0' => 'node_type_a'), array('query' => array('_format' => 'json'))), 'MISS');
  }

  /**
   * Verify that when loading a given page, it's a page cache hit or miss.
   *
   * @param \Drupal\Core\Url $url
   *   The page for this URL will be loaded.
   * @param string $hit_or_miss
   *   'HIT' if a page cache hit is expected, 'MISS' otherwise.
   *
   * @param array|FALSE $tags
   *   When expecting a page cache hit, you may optionally specify an array of
   *   expected cache tags. While FALSE, the cache tags will not be verified.
   */
  protected function verifyPageCache(Url $url, $hit_or_miss, $tags = FALSE) {
    $this->drupalGet($url);
    $message = new FormattableMarkup('Page cache @hit_or_miss for %path.', array('@hit_or_miss' => $hit_or_miss, '%path' => $url->toString()));
    $this->assertEquals($hit_or_miss, $this->getSession()->getResponseHeader('X-Drupal-Cache'), $message);

    if ($hit_or_miss === 'HIT' && is_array($tags)) {
      $absolute_url = $url->setAbsolute()->toString();
      $cid_parts = array($absolute_url, 'html');
      $cid = implode(':', $cid_parts);
      $cache_entry = \Drupal::cache('render')->get($cid);
      sort($cache_entry->tags);
      $tags = array_unique($tags);
      sort($tags);
      $this->assertSame($cache_entry->tags, $tags);
    }
  }
}

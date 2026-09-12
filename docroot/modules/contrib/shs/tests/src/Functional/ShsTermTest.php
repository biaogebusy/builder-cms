<?php

namespace Drupal\Tests\shs\Functional;

use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Test term functions in SHS.
 *
 * @group shs
 */
class ShsTermTest extends ShsTestBase {

  use ShsTestTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'shs',
    'dynamic_page_cache',
  ];

  /**
   * Tests getting the first level of terms.
   */
  public function testFirstLevel(): void {
    // Get all predefined terms with no parent.
    $expected = array_filter($this->getTermStructure(), function ($parent) {
      return empty($parent);
    });

    $field_name = 'shs-' . strtr($this->fieldName, ['_' => '-']);
    $request_url = "shs-term-data/{$field_name}/{$this->vocabulary->id()}/0";

    // Request term data.
    $data = $this->drupalGetJson($request_url);

    $expected_count = count($expected);
    $this->assertCount($expected_count, $data, "JSON callback returned {$expected_count} result");

    // Check if returned data is correct.
    $terms_with_children_expected = [
      'aaa 1',
      'aaa 3',
    ];
    // Get keys of all returned terms where "hasChildren" is true.
    $keys = array_flip(array_keys(array_column($data, 'hasChildren'), TRUE, TRUE));
    $terms_with_children = array_intersect_key($data, $keys);
    $this->assertCount(2, $terms_with_children, 'Expected 2 terms having children');

    foreach ($terms_with_children_expected as $name) {
      $term_exists = array_keys(array_column($terms_with_children, 'name'), $name, TRUE);
      $this->assertCount(1, $term_exists, "Expected term '{$name}' to have children.");
    }
  }

  /**
   * Tests getting children of a specific term.
   */
  public function testChildren(): void {
    $parent = $this->termIds['aaa 1'];

    $field_name = 'shs-' . strtr($this->fieldName, ['_' => '-']);
    $request_url = "shs-term-data/{$field_name}/{$this->vocabulary->id()}/{$parent}";

    // Request term data.
    $data = $this->drupalGetJson($request_url);

    $this->assertCount(4, $data, "JSON callback returned 4 result");

  }

  /**
   * Tests term data caching when content translation is not installed.
   */
  public function testCacheWithoutTranslation(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('language'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_translation'));

    $field_name = 'shs-' . strtr($this->fieldName, ['_' => '-']);
    $request_url = "shs-term-data/{$field_name}/{$this->vocabulary->id()}/0";

    $data = $this->drupalGetJson($request_url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'MISS');
    $this->assertContains('aaa 1', array_column($data, 'name'));

    $cached_data = $this->drupalGetJson($request_url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'HIT');
    $this->assertSame($data, $cached_data);
  }

  /**
   * Tests caching of responses.
   */
  public function testRoleCache():void {
    $user_with_all_access = $this->drupalCreateUser(['administer taxonomy']);
    $user_with_view_access = $this->drupalCreateUser(['access content']);

    // Unpublish one of the terms.
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($this->termIds['aaa 1']);
    $term->setUnpublished();
    $term->save();

    // The unpublished term should be in the json for the administrator.
    $this->drupalLogin($user_with_all_access);

    $field_name = 'shs-' . strtr($this->fieldName, ['_' => '-']);
    $request_url = "shs-term-data/{$field_name}/{$this->vocabulary->id()}/0";

    $data = $this->drupalGetJson($request_url);

    $this->assertCount(4, $data);

    $names = array_map(function ($a) {
      return $a['name'];
    }, $data);
    $this->assertContains('aaa 1', $names);

    // The other user should not see the unpublished term.
    $this->drupalLogin($user_with_view_access);

    $data = $this->drupalGetJson($request_url);

    $this->assertCount(3, $data);

    $names = array_map(function ($a) {
      return $a['name'];
    }, $data);
    $this->assertNotContains('aaa 1', $names);
  }

  /**
   * Tests editing a node after its referenced term has been deleted.
   */
  public function testEditNodeWithDeletedTerm(): void {
    $term_delete = $this->createTerm($this->vocabulary, ['name' => 'term to delete']);

    $node = $this->drupalCreateNode([
      'type' => 'article',
      $this->fieldName => ['target_id' => $term_delete->id()],
    ]);

    $term_delete->delete();

    $this->assertFalse($node->{$this->fieldName}->isEmpty());

    $editor = $this->drupalCreateUser(['edit any article content']);
    $this->drupalLogin($editor);

    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([], 'Save');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("{$node->getTitle()} has been updated.");
    $this->assertSession()->elementNotExists('css', '[data-drupal-messages] .messages--error');

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node_storage->resetCache([$node->id()]);
    $node = $node_storage->load($node->id());
    $this->assertTrue($node->{$this->fieldName}->isEmpty());
  }

}

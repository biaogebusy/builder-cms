<?php

namespace Drupal\Tests\pathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config dependency handling for PathautoPattern entities.
 *
 * @group pathauto
 * @see https://www.drupal.org/project/pathauto/issues/3072876
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoPatternDependencyTest extends KernelTestBase {

  use PathautoTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'user',
    'node',
    'path',
    'path_alias',
    'pathauto',
    'token',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['pathauto', 'system', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Tests that calculateDependencies includes bundle config dependencies.
   */
  public function testCalculateDependenciesIncludesBundles(): void {
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => [
        'article' => 'article',
        'page' => 'page',
      ],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();

    // Reload to get fresh dependencies.
    $pattern = PathautoPattern::load($pattern->id());
    $dependencies = $pattern->getDependencies();

    $this->assertContains('node.type.article', $dependencies['config']);
    $this->assertContains('node.type.page', $dependencies['config']);
    $this->assertContains('node', $dependencies['module']);
  }

  /**
   * Tests removing one bundle from a multi-bundle condition.
   */
  public function testRemoveSingleBundleFromMultiple(): void {
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => [
        'article' => 'article',
        'page' => 'page',
      ],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();
    $pattern_id = $pattern->id();

    // Delete the article node type.
    NodeType::load('article')->delete();

    // Pattern should still exist with only the 'page' bundle.
    $pattern = PathautoPattern::load($pattern_id);
    $this->assertNotNull($pattern, 'Pattern was not deleted.');

    // Check the condition was updated.
    $conditions = $pattern->getSelectionConditions();
    $this->assertCount(1, $conditions, 'One condition remains.');

    $condition = $conditions->getIterator()->current();
    $bundles = $condition->getConfiguration()['bundles'];
    $this->assertArrayHasKey('page', $bundles);
    $this->assertArrayNotHasKey('article', $bundles);

    // Verify dependencies were updated.
    $dependencies = $pattern->getDependencies();
    $this->assertContains('node.type.page', $dependencies['config']);
    $this->assertNotContains('node.type.article', $dependencies['config']);
  }

  /**
   * Tests removing the last bundle removes the condition entirely.
   */
  public function testRemoveLastBundleRemovesCondition(): void {
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => [
        'article' => 'article',
      ],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();
    $pattern_id = $pattern->id();

    // Delete the article node type.
    NodeType::load('article')->delete();

    // Pattern should still exist but with no conditions.
    $pattern = PathautoPattern::load($pattern_id);
    $this->assertNotNull($pattern, 'Pattern was not deleted.');
    $this->assertCount(0, $pattern->getSelectionConditions(), 'No conditions remain.');
  }

  /**
   * Tests that the pattern survives bundle removal (is not deleted).
   */
  public function testPatternNotDeletedOnBundleRemoval(): void {
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => [
        'article' => 'article',
      ],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();
    $pattern_id = $pattern->id();

    // Delete the article node type.
    NodeType::load('article')->delete();

    // The pattern must still be loadable but disabled.
    $pattern = PathautoPattern::load($pattern_id);
    $this->assertNotNull($pattern, 'Pattern entity still exists.');
    $this->assertFalse($pattern->status(), 'Pattern is disabled after losing all bundle conditions.');
  }

}

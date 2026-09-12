<?php

declare(strict_types=1);

namespace Drupal\Tests\pathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the post update that removes uuid from condition plugin config.
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoRemoveUuidUpdateTest extends KernelTestBase {

  use PathautoTestHelperTrait;

  /**
   * Disable schema validation because we intentionally write invalid config.
   *
   * The test injects uuid keys into condition plugin config to simulate
   * legacy data. In Drupal 11.4+, this key is no longer in the schema,
   * so strict validation would reject it before the post update runs.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

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
  }

  /**
   * Tests that the post update removes uuid keys from selection criteria.
   *
   * Creates a pathauto pattern with a bundle condition, then injects
   * a uuid key into each condition's config via raw config storage
   * (simulating pre-existing data). Runs the post update function and
   * asserts that the uuid keys are removed while the rest of the
   * condition config is preserved.
   */
  public function testRemoveUuidFromConditionConfig(): void {
    // Create a pattern with a bundle condition.
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => ['article' => 'article'],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();
    $pattern_id = $pattern->id();

    // Inject uuid keys into selection_criteria via raw config, simulating
    // the legacy data that core 11.3.4 schema change exposed.
    $config = $this->config('pathauto.pattern.' . $pattern_id);
    $selection_criteria = $config->get('selection_criteria');
    foreach ($selection_criteria as $uuid => $condition) {
      $selection_criteria[$uuid]['uuid'] = $uuid;
    }
    $config->set('selection_criteria', $selection_criteria)->save();

    // Verify the uuid key is present before the update.
    $config = $this->config('pathauto.pattern.' . $pattern_id);
    $selection_criteria = $config->get('selection_criteria');
    foreach ($selection_criteria as $condition) {
      $this->assertArrayHasKey('uuid', $condition);
    }

    // Run the post update.
    $sandbox = [];
    require_once __DIR__ . '/../../../pathauto.post_update.php';
    pathauto_post_update_remove_uuid_config_key($sandbox);

    // Reload and verify uuid keys are gone.
    $pattern = PathautoPattern::load($pattern_id);
    $selection_criteria = $pattern->get('selection_criteria');
    foreach ($selection_criteria as $condition) {
      $this->assertArrayNotHasKey('uuid', $condition, 'uuid key was removed from condition config.');
      // Verify the rest of the condition is intact.
      $this->assertEquals('entity_bundle:node', $condition['id']);
      $this->assertArrayHasKey('bundles', $condition);
      $this->assertEquals(['article' => 'article'], $condition['bundles']);
    }
  }

  /**
   * Tests that patterns without uuid keys in conditions are not modified.
   *
   * Creates a pattern with a bundle condition that has no uuid key,
   * runs the post update, and verifies the pattern is unchanged.
   */
  public function testSkipsPatternWithoutUuid(): void {
    $pattern = $this->createPattern('node', '/content/[node:title]');
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => ['article' => 'article'],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();
    $pattern_id = $pattern->id();

    // Get the config before the update.
    $config_before = $this->config('pathauto.pattern.' . $pattern_id)->get('selection_criteria');

    // Run the post update — should be a no-op.
    $sandbox = [];
    require_once __DIR__ . '/../../../pathauto.post_update.php';
    pathauto_post_update_remove_uuid_config_key($sandbox);

    // Config should be identical.
    $config_after = $this->config('pathauto.pattern.' . $pattern_id)->get('selection_criteria');
    $this->assertEquals($config_before, $config_after);
  }

}

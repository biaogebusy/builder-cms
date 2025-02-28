<?php

namespace Drupal\Tests\votingapi\Kernel\migrate;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests D7 rate source plugin.
 *
 * @group votingapi
 */
class VoteMigrationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['votingapi'];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath(): string {
    return __DIR__ . '/../../../fixtures/drupal7.php';
  }

  /**
   * Tests migration.
   */
  public function testVoteMigration(): void {
    $this->installEntitySchema('vote');
    // This demonstrates that only comments belonging to articles are migrated.
    // d7_vote_type migration needs to be executed before any d7_vote migration.
    $this->executeMigrations(['d7_vote_type', 'd7_vote:comment:article']);
    $storage = \Drupal::entityTypeManager()->getStorage('vote');
    assert($storage instanceof EntityStorageInterface);
    $votes = $storage->loadMultiple();
    $this->assertCount(3, $votes);
    $array_1 = $votes[1]->toArray();
    $test_1 = [
      'id' => [['value' => '1']],
      'type' => [['target_id' => 'vote']],
      'entity_type' => [['value' => 'comment']],
      'entity_id' => [['target_id' => '6']],
      'value' => [['value' => '77']],
      'value_type' => [['value' => 'percent']],
      'user_id' => [['target_id' => '1']],
      'timestamp' => [['value' => '1635760436']],
      'vote_source' => [['value' => '127.0.0.1']],
    ];
    $this->assertEquals(
      array_diff_key(
        $array_1,
        ['uuid' => 'uuid']
      ),
      $test_1
    );
    // Migrates all votes present.
    $this->executeMigrations(['d7_vote']);
    $storage = \Drupal::entityTypeManager()->getStorage('vote');
    assert($storage instanceof EntityStorageInterface);
    $votes = $storage->loadMultiple();
    $this->assertCount(10, $votes);
  }

  /**
   * Tests Vote Type migration.
   */
  public function testVoteTypeMigration(): void {
    $vote_types_before_migration = \Drupal::entityTypeManager()->getStorage('vote_type');
    $vote_type_before = $vote_types_before_migration->loadMultiple();
    $this->assertCount(0, $vote_type_before);
    $this->executeMigrations(['d7_vote_type']);
    $vote_types_after_migration = \Drupal::entityTypeManager()->getStorage('vote_type');
    $vote_type_after = $vote_types_after_migration->loadMultiple();
    $this->assertCount(1, $vote_type_after);
  }

}

<?php

namespace Drupal\Tests\eck\Kernel\Plugin\migrate\source;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateException;
use Drupal\Tests\eck\Kernel\Migrate\d7\MigrateEckTestBase;

/**
 * Tests check requirements for comment type source plugin.
 *
 * @group eck
 */
class EckEntityExceptionTest extends MigrateEckTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eck'];

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->migrationPluginManager = \Drupal::service('plugin.manager.migration');
  }

  /**
   * Tests checkRequirements.
   */
  public function testEckEntityCheckRequirements() {
    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage("ECK table for 'does_not_exist' does not exist");
    $migration = $this->getMigration('d7_eck');
    $definition = $migration->getPluginDefinition();
    $definition['source'] = [
      'plugin' => 'd7_eck_entity',
      'entity_type' => 'does_not_exist',
      'bundle' => 'simple_entity',
    ];
    $migration = $this->migrationPluginManager
      ->createInstance('d7_eck:simple_entity:simple_entity', $definition);
    $migration
      ->getSourcePlugin()
      ->checkRequirements();
  }

  /**
   * Tests thrown exceptions when node or comment aren't enabled on source.
   *
   * @param mixed $entity_type
   *   The entity type.
   * @param mixed $bundle
   *   The bundle.
   * @param string $exception_message
   *   The expected exception message.
   *
   * @dataProvider providerTestEckEntityConstructor
   */
  public function testEckEntityConstructor($entity_type, $bundle, $exception_message) {
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage($exception_message);
    $migration = $this->getMigration('d7_eck:simple_entity:simple_entity');

    $definition = $migration->getPluginDefinition();
    $definition['source'] = [
      'plugin' => 'd7_eck_entity',
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ];
    $migration = $this->migrationPluginManager
      ->createInstance('d7_eck:simple_entity:simple_entity', $definition);
    $migration->getSourcePlugin();
  }

  /**
   * Test cases for ::testEckEntityConstructor().
   */
  public function providerTestEckEntityConstructor() {
    return [
      'entity array' => [
        [],
        'string',
        "The entity_type must be a string",
      ],
    ];
  }

}

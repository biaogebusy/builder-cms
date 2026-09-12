<?php

namespace Drupal\Tests\pathauto\Kernel;

use Drupal\Component\Utility\Crypt;
use Drupal\KernelTests\KernelTestBase;
use Drupal\pathauto\PathautoState;
use Drupal\pathauto_string_id_test\Entity\PathautoStringId;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests auto-aliasing of entities that use string IDs.
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoEntityWithStringIdTest extends KernelTestBase {

  use PathautoTestHelperTrait;

  /**
   * The alias type plugin instance.
   *
   * @var \Drupal\pathauto\AliasTypeBatchUpdateInterface
   */
  protected $aliasType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'pathauto_string_id_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'pathauto']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('pathauto_string_id_test');
    $this->createPattern('pathauto_string_id_test', '/[pathauto_string_id_test:name]');
    /** @var \Drupal\pathauto\AliasTypeManager $alias_type_manager */
    $alias_type_manager = $this->container->get('plugin.manager.alias_type');
    $this->aliasType = $alias_type_manager->createInstance('canonical_entities:pathauto_string_id_test');
    // Kernel tests default to in-memory key-value storage. Use the
    // database-backed service to validate that Pathauto state can be
    // persisted to the database without errors.
    $this->container->set('keyvalue', $this->container->get('keyvalue.database'));
  }

  /**
   * Test aliasing entities with long string ID.
   *
   * @param string|int $id
   *   The entity ID.
   * @param string $expected_key
   *   The expected key for 'pathauto_state.*' collections.
   *
   * @dataProvider entityWithStringIdProvider
   */
  public function testEntityWithStringId($id, $expected_key) {
    $entity = PathautoStringId::create([
      'id' => $id,
      'name' => $name = $this->randomMachineName(),
    ]);
    $entity->save();

    // Check that the path was generated.
    $this->assertEntityAlias($entity, mb_strtolower("/$name"));
    // Check that the path auto state was saved with the expected key.
    $value = \Drupal::keyValue('pathauto_state.pathauto_string_id_test')->get($expected_key);
    $this->assertEquals(PathautoState::CREATE, $value);

    $context = [];
    // Batch delete uses the key-value store collection 'pathauto_state.*. We
    // test that after a bulk delete all aliases are removed. Running only once
    // the batch delete process is enough as the batch size is 100.
    $this->aliasType->batchDelete($context);

    // Check that the paths were removed on batch delete.
    $this->assertNoEntityAliasExists($entity, "/$name");
  }

  /**
   * Provides test cases for ::testEntityWithStringId().
   *
   * @see \Drupal\Tests\pathauto\Kernel\PathautoEntityWithStringIdTest::testEntityWithStringId()
   */
  public static function entityWithStringIdProvider() {
    return [
      'ascii with less or equal 128 chars' => [
        str_repeat('a', 128), str_repeat('a', 128),
      ],
      'ascii with over 128 chars' => [
        str_repeat('a', 191), Crypt::hashBase64(str_repeat('a', 191)),
      ],
      'non-ascii with less or equal 128 chars' => [
        str_repeat('社', 128), Crypt::hashBase64(str_repeat('社', 128)),
      ],
      'non-ascii with over 128 chars' => [
        str_repeat('社', 191), Crypt::hashBase64(str_repeat('社', 191)),
      ],
      'simulating an integer id' => [
        123, '123',
      ],
    ];
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\entity_test\Entity\EntityTestNoUuid;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test entity without UUID.
 *
 * Tests that Entity Share does not provoke error when deleting entity without
 * UUID.
 *
 * @group entity_share
 * @group entity_share_client
 */
class EntityNoUuidDeletionTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'jsonapi',
    'entity_share',
    'entity_share_client',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_no_uuid');
    $this->installEntitySchema('entity_import_status');
  }

  /**
   * Tests that deleting an entity without UUID is possible.
   */
  public function testEntityDeletion() {
    $entity = EntityTestNoUuid::create([
      'name' => 'Test deletion',
    ]);
    $entity->save();
    $entity->delete();
    $this->assertTrue(TRUE, 'If we can reach this assert, it means that it is possible to delete the entity without UUID.');
  }

}

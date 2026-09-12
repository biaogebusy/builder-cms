<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Tests\entity_share_client\Mocks\EntityTestMulRevChangedWithRevisionLogFix;

/**
 * Tests the revision processor.
 *
 * @group entity_share
 * @group entity_share_client
 */
class RevisionPullTest extends PullKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Use the test entity type which has a revision log.
    $this->installEntitySchema('entity_test_mulrev_changed_rev');
    entity_test_create_bundle('alpha', entity_type: 'entity_test_mulrev_changed_rev');

    $this->createChannel('entity_test_mulrev_changed_rev', 'alpha', 'en');
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);

    $service_definition = $container->getDefinition('entity_type.manager');
    $service_definition->setClass(TestEntityTypeManager::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings(): array {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['revision'] = [
      'weights' => static::PLUGIN_DEFINITION_STAGES,
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesData(): array {
    return [
      'entity_test_mulrev_changed_rev' => [
        'en' => [
          'source' => [
            'name' => 'one',
            'type' => 'alpha',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests creating revisions for a new entity.
   */
  public function testNewEntityRevisions(): void {
    // Mock the current time so the source entity has this as its created time.
    $this->setMockedTime(10000);

    $this->prepareContent();

    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    // We have 2 entities, the source and the pulled.
    $entity_storage = $this->entityTypeManager->getStorage('entity_test_mulrev_changed_rev');
    $entities = $entity_storage->loadMultiple();
    $this->assertCount(2, $entities);

    $pulled_entity = $this->loadPulledEntity('entity_test_mulrev_changed_rev', 'source');

    $revisions = $this->getAllRevisisions($pulled_entity);
    $this->assertCount(1, $revisions);

    $revision_id = array_key_last($revisions);
    $revision_1 = $entity_storage->loadRevision($revision_id);

    $this->assertEquals('Auto created revision during Entity Share synchronization.', $revision_1->revision_log_message->value, 'The pulled entity has the revision log message.');
    $this->assertEquals(10000, $revision_1->revision_created->value);

    // Update the mocked time.
    $this->setMockedTime(20000);

    // Update the source entity and pull again.
    $source_entity = $this->entityRepository->loadEntityByUuid('entity_test_mulrev_changed_rev', 'source');
    $source_entity->name = 'two';
    $source_entity->save();

    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    $pulled_entity = $this->reloadEntity($pulled_entity);

    // We still have only 2 entities, the source and the pulled.
    $entity_storage = $this->entityTypeManager->getStorage('entity_test_mulrev_changed_rev');
    $entities = $entity_storage->loadMultiple();
    $this->assertCount(2, $entities);

    $revisions = $this->getAllRevisisions($pulled_entity);

    // We now have two revisions of the pulled entity.
    $this->assertCount(2, $revisions);

    $revision_id = array_key_last($revisions);
    $revision_2 = $entity_storage->loadRevision($revision_id);

    $this->assertEquals('Auto created revision during Entity Share synchronization.', $revision_2->revision_log_message->value, 'The new revision of the pulled entity has the revision log message.');
    $this->assertEquals(20000, $revision_2->revision_created->value);

    // Update the source entity, making a new revision this time.
    $source_entity = $this->loadSourceEntity('entity_test_mulrev_changed_rev', 'source');
    $source_entity->setNewRevision();
    $source_entity->name = 'three';
    $source_entity->revision_log_message = 'Revision 3';
    $source_entity->save();

    // Change the import processor settings for the log message format.
    $import_processor_settings = $this->importConfig->get('import_processor_settings');
    $import_processor_settings['revision']['revision_log_message'] = 'user_automatic';
    $this->importConfig->set('import_processor_settings', $import_processor_settings);
    $this->importConfig->save();

    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    $pulled_entity = $this->reloadEntity($pulled_entity);
    $this->assertEquals("Revision 3\nAuto created revision during Entity Share synchronization.", $pulled_entity->revision_log_message->value, 'The new revision of the pulled entity has the revision log message.');

    // Update the source entity, making a new revision this time.
    $source_entity = $this->loadSourceEntity('entity_test_mulrev_changed_rev', 'source');
    $source_entity->setNewRevision();
    $source_entity->name = 'four';
    $source_entity->revision_log_message = 'Revision 4';
    $source_entity->save();

    // Change the import processor settings for the log message format.
    $import_processor_settings = $this->importConfig->get('import_processor_settings');
    $import_processor_settings['revision']['revision_log_message'] = 'automatic_user';
    $this->importConfig->set('import_processor_settings', $import_processor_settings);
    $this->importConfig->save();

    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    $pulled_entity = $this->reloadEntity($pulled_entity);
    $this->assertEquals("Auto created revision during Entity Share synchronization.\nRevision 4", $pulled_entity->revision_log_message->value, 'The new revision of the pulled entity has the revision log message.');
  }

  /**
   * Tests creating revisions for an entity that already exists locally.
   *
   * This is for the use case where two sites start with the same database, and
   * so have the same entity prior to an Entity Share pull.
   */
  public function testExistingEntityRevisions(): void {
    // Mock the current time.
    $this->setMockedTime(10000);

    $entity_storage = $this->entityTypeManager->getStorage('entity_test_mulrev_changed_rev');

    $this->prepareContent();

    // Create a local copy of the entity.
    $local_entity = $entity_storage->create([
      'uuid' => 'PULLED-source',
      'type' => 'alpha',
      'name' => 'local',
    ]);
    $local_entity->save();

    $this->setMockedTime(20000);

    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    $pulled_entity = $this->loadPulledEntity('entity_test_mulrev_changed_rev', 'source');
    $this->assertEquals($local_entity->id(), $pulled_entity->id());

    $revisions = $this->getAllRevisisions($pulled_entity);
    $this->assertCount(2, $revisions);

    $revision_id = array_key_last($revisions);
    $revision_2 = $entity_storage->loadRevision($revision_id);

    $this->assertEquals('Auto created revision during Entity Share synchronization.', $revision_2->revision_log_message->value);
    $this->assertEquals(20000, $revision_2->revision_created->value);
  }

  /**
   * Helper to mock the current time.
   *
   * @param integer $timestamp
   *   The timestamp to set as the current time.
   */
  protected function setMockedTime(int $timestamp) {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn($timestamp);
    $time->getCurrentTime()->willReturn($timestamp);
    $this->container->set('datetime.time', $time->reveal());
  }

  /**
   * Gets the entity query result for all revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get revisions for.
   *
   * @return array
   *   The result of the entity query: an array whose keys are revisions IDs and
   *   whose values are the entity ID.
   */
  protected function getAllRevisisions(ContentEntityInterface $entity): array {
    $entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    $revisions = $entity_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('id', $entity->id())
      ->execute();

    return $revisions;
  }

}

/**
 * Workarounds for core bugs.
 *
 * @todo Remove when https://www.drupal.org/project/drupal/issues/3546966 is
 * fixed in our lowest supported core version.
 */
class TestEntityTypeManager extends EntityTypeManager {

  protected function alterDefinitions(&$definitions) {
    parent::alterDefinitions($definitions);

    $definitions['entity_test_mulrev_changed_rev']->set('admin_permission', 'administer entity_test content');
    $definitions['entity_test_mulrev_changed_rev']->set('class', EntityTestMulRevChangedWithRevisionLogFix::class);
  }

}

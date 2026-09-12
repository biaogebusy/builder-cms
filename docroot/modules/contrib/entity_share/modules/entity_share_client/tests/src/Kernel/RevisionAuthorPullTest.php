<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Tests\entity_share_client\Mocks\EntityTestMulRevChangedWithRevisionLogFix;

/**
 * Tests the revision author processor.
 *
 * @group entity_share
 * @group entity_share_client
 */
class RevisionAuthorPullTest extends PullKernelTestBase {

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

    // Use the test entity type which has revisions with authors.
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
    $service_definition->setClass(RevisionAuthorTestEntityTypeManager::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings(): array {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['revision'] = [
      'weights' => static::PLUGIN_DEFINITION_STAGES,
    ];
    $processors['revision_author'] = [
      'weights' => static::PLUGIN_DEFINITION_STAGES,
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesData(): array {
    return [
      'user' => [
        'en' => [
          'author' => [
            'name' => 'Author',
          ],
          'editor' => [
            'name' => 'Editor',
          ],
          // Make local copies of the author, with the mocked UUID, to simulate
          // the same users existing on both source and client.
          static::MOCKED_UUID_PREFIX . 'author' => [
            'name' => 'Local author',
          ],
          static::MOCKED_UUID_PREFIX . 'editor' => [
            'name' => 'Local editor',
          ],
        ],
      ],
      'entity_test_mulrev_changed_rev' => [
        'en' => [
          'source' => [
            'name' => 'one',
            'type' => 'alpha',
            'user_id' => [
              ['user', 'author'],
            ],
            // Set the revision author on the first revision: this is what would
            // happen with an entity created in the UI.
            'revision_user' => [
              ['user', 'author'],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Tests creating revisions for a new entity. TODO
   */
  public function testRevisionAuthor(): void {
    $this->prepareContent();

    $author = $this->loadSourceEntity('user', 'author');
    $editor = $this->loadSourceEntity('user', 'editor');

    $source_entity = $this->loadSourceEntity('entity_test_mulrev_changed_rev', 'source');

    $this->assertEquals($author->id(), $source_entity->user_id->target_id, 'The source entity has the author set.');
    $this->assertEquals($author->id(), $source_entity->revision_user->target_id, 'The source entity has the revision author set.');

    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    $pulled_entity = $this->loadPulledEntity('entity_test_mulrev_changed_rev', 'source');
    $this->assertEquals(static::MOCKED_UUID_PREFIX . 'author', $pulled_entity->revision_user->entity->uuid(), 'The pulled entity has the revision author set to the local user.');

    // Make a new revision of the source entity, by a new author.
    $source_entity->setNewRevision();
    $source_entity->setRevisionUserId($editor->id());
    $source_entity->revision_log_message = 'Revision 2';
    $source_entity->save();

    // Pull again.
    $this->pullEntities('entity_test_mulrev_changed_rev_alpha_en', ['source']);

    $pulled_entity = $this->loadPulledEntity('entity_test_mulrev_changed_rev', 'source');
    $this->assertEquals(static::MOCKED_UUID_PREFIX . 'editor', $pulled_entity->revision_user->entity->uuid(), 'The pulled entity has the revision author set to the local user.');
  }

}

/**
 * Workarounds for core bug.
 *
 * @todo Remove when https://www.drupal.org/project/drupal/issues/3546966 is
 * fixed in our lowest supported core version.
 */
class RevisionAuthorTestEntityTypeManager extends EntityTypeManager {

  protected function alterDefinitions(&$definitions) {
    parent::alterDefinitions($definitions);

    $definitions['entity_test_mulrev_changed_rev']->set('admin_permission', 'administer entity_test content');
    $definitions['entity_test_mulrev_changed_rev']->set('class', EntityTestMulRevChangedWithRevisionLogFix::class);
  }

}

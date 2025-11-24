<?php

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Tests the redirect_processor processor plugin.
 *
 * @group entity_share
 */
class RedirectPullTest extends PullKernelTestBase {

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'link',
    'path_alias',
    'redirect',
    'entity_test',
  ];

  /**
   * The redirect repository service.
   *
   * @var \Drupal\redirect\RedirectRepository
   */
  protected $redirectRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->redirectRepository = $this->container->get('redirect.repository');

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('entity_test');

    // We need to hack the storage schema for the 'redirect' entity type,
    // because its original one defines the 'hash' field as a unique database
    // index, and our test will create two redirects with the same value for
    // their source field.
    $redirect_entity_type = $this->entityTypeManager->getDefinition('redirect');
    $redirect_entity_type->setHandlerClass('storage_schema', SqlContentEntityStorageSchema::class);

    $this->installEntitySchema('redirect');

    $this->createChannel('entity_test', 'entity_test', 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings(): array {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['redirect_processor'] = [
      'weights' => static::PLUGIN_DEFINITION_STAGES,
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesData(): array {
    return [
      'entity_test' => [
        'en' => [
          'source' => [
            'name' => 'source',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests pulling redirects that point to a pulled entity.
   */
  public function testRedirect(): void {
    $entity_test_storage = $this->entityTypeManager->getStorage('entity_test');
    $redirect_storage = $this->entityTypeManager->getStorage('redirect');

    $this->prepareContent();

    // Create redirects whose destinatin is the test entity.
    // Same language as the entity, using the internal: uri schema.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $redirect_storage->create();
    $redirect->setSource('some-url-internal-language');
    $redirect->setRedirect('/entity_test/1');
    // Give the source redirect a fake UUID.
    $redirect->uuid = 'redirect-internal-language';
    $redirect->save();

    // Same language as the entity, using the entity: uri schema.
    $redirect = $redirect_storage->create();
    $redirect->setSource('some-url-entity-language');
    // Can't use setRedirect(), as that will prefix with 'internal:'.
    // See https://www.drupal.org/project/redirect/issues/3534901.
    $redirect->set('redirect_redirect', 'entity:entity_test/1');
    // Give the source redirect a fake UUID.
    $redirect->uuid = 'redirect-entity-language';
    $redirect->save();

    // Language undefined.
    $redirect = $redirect_storage->create();
    $redirect->setSource('some-url-internal-und');
    $redirect->setRedirect('/entity_test/1');
    // Give the source redirect a fake UUID.
    $redirect->uuid = 'redirect-und';
    $redirect->language = 'und';
    $redirect->save();

    // Pull the channel. This should create a pulled test entity, and 3 pulled
    // redirects.
    $this->pullChannel('entity_test_entity_test_en');

    $test_entities = $entity_test_storage->loadMultiple();
    $this->assertCount(2, $test_entities);
    $redirects = $redirect_storage->loadMultiple();
    $this->assertCount(6, $redirects);

    // Check the pulled redirects.
    // We have two redirects for each source redirect path (since the redirect
    // API will include both the source entity and the pulled entity).
    // The source path does not have an initial '/'.
    $source_paths = [
      'some-url-internal-language',
      'some-url-entity-language',
      'some-url-internal-und',
    ];
    foreach ($source_paths as $source_path) {
      $redirects = $this->redirectRepository->findBySourcePath($source_path);
      $this->assertEquals(2, count($redirects));
    }

    // We have 3 pulled redirects whose destination is the pulled entity's URI.
    $pulled_test_entity = $this->loadPulledEntity('entity_test', 'source');
    $redirects = $this->redirectRepository->findByDestinationUri(['internal:/entity_test/' . $pulled_test_entity->id()]);
    $this->assertEquals(3, count($redirects));
  }

}

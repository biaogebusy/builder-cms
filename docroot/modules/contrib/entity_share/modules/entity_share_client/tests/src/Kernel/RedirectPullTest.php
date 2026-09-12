<?php

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager;
use Drupal\entity_share_client\RuntimeImportContext;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Tests the redirect_processor processor plugin.
 *
 * @group entity_share
 */
class RedirectPullTest extends PullKernelTestBase {

  use PathAliasTestTrait;

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'link',
    'path',
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

    // We don't need to set auto_redirect to FALSE in redirect settings, as that
    // only affects updating path_alias entities, not creating new one.
    $this->installEntitySchema('redirect');

    $this->createChannel('entity_test', 'entity_test', 'en');
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);

    $service_definition = $container->getDefinition('entity_type.manager');
    $service_definition->setClass(RedirectPullTestEntityTypeManager::class);

    $service_definition = $container->getDefinition('plugin.manager.entity_share_client_import_processor');
    $service_definition->setClass(RedirectPullTestImportProcessorPluginManager::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings(): array {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['redirect_processor'] = [
      'weights' => static::PLUGIN_DEFINITION_STAGES,
    ];
    $processors['redirect_hash_filter'] = [
      'weights' => static::PLUGIN_DEFINITION_STAGES,
    ];
    // Add our test processor.
    $processors['redirect_mocker'] = [
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
    // Set our test processor to add to the source query of incoming redirects,
    // so that they don't get discarded by the redirect processor for having an
    // existing redirect hash.
    TestRedirectSourceMocker::$addToQuery = TRUE;

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

    // Redirect to the entity's path alias.
    $this->createPathAlias('/entity_test/1', '/entity-alias');

    $redirect = $redirect_storage->create();
    $redirect->setSource('some-url-internal-alias');
    $redirect->setRedirect('/entity-alias');
    // Give the source redirect a fake UUID.
    $redirect->uuid = 'redirect-alias';
    $redirect->save();

    // Pull the channel. This should create a pulled test entity, and 3 pulled
    // redirects.
    $this->pullChannel('entity_test_entity_test_en');

    $test_entities = $entity_test_storage->loadMultiple();
    $this->assertCount(2, $test_entities);
    $redirects = $redirect_storage->loadMultiple();
    $this->assertCount(8, $redirects);

    // Check the source paths of the pulled redirects.
    // We have two redirects for each source redirect path (since the redirect
    // API will include both the source entity and the pulled entity).
    // The source path does not have an initial '/'.
    $source_paths = [
      'some-url-internal-language',
      'some-url-entity-language',
      'some-url-internal-und',
      'some-url-internal-alias',
    ];
    foreach ($source_paths as $source_path) {
      $redirects = $this->redirectRepository->findBySourcePath($source_path);
      $this->assertEquals(2, count($redirects), "There are 2 redirects from path '{$source_path}'.");
    }

    // Check the destination paths of the pulled redirects.
    $pulled_test_entity = $this->loadPulledEntity('entity_test', 'source');
    $pulled_redirects = $this->redirectRepository->findByDestinationUri(['internal:/entity_test/' . $pulled_test_entity->id()]);
    $this->assertCount(2, $pulled_redirects, "There are 2 pulled redirects whose destination is the pulled entity's URI.");

    $pulled_redirects = $this->redirectRepository->findByDestinationUri(['entity:entity_test/' . $pulled_test_entity->id()]);
    $this->assertCount(1, $pulled_redirects, "There is 1 pulled redirects whose destination is the pulled entity's URI.");

    // We have to load the pulled redirect specifically, as the source redirect
    // has the same redirect URI.
    $pulled_redirect = $this->loadPulledEntity('redirect', 'redirect-alias');
    $this->assertEquals('internal:/entity-alias', $pulled_redirect->getRedirect()['uri']);
  }

  /**
   * Tests skipping a redirect with a matching hash.
   */
  public function testSkippedRedirect(): void {
    // Set our test processor to not do anything, as we want to test the
    // skipping of redirects with matching redirect hashes.
    TestRedirectSourceMocker::$addToQuery = FALSE;

    $entity_test_storage = $this->entityTypeManager->getStorage('entity_test');
    $redirect_storage = $this->entityTypeManager->getStorage('redirect');

    $this->prepareContent();

    $redirect = $redirect_storage->create();
    $redirect->setSource('some-url-internal-language');
    $redirect->setRedirect('/entity_test/1');
    // Give the source redirect a fake UUID.
    $redirect->uuid = 'redirect-internal-language';
    $redirect->save();

    // Pull the channel. This should create a pulled test entity, and 3 pulled
    // redirects.
    $this->pullChannel('entity_test_entity_test_en');

    $test_entities = $entity_test_storage->loadMultiple();
    $this->assertCount(2, $test_entities);
    $redirects = $redirect_storage->loadMultiple();
    $this->assertCount(1, $redirects, 'No redirect was pulled, because a redirect with the source URI already exists.');
  }

}

/**
 * Switch the entity class for the entity_test entity type.
 */
class RedirectPullTestEntityTypeManager extends EntityTypeManager {

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    parent::alterDefinitions($definitions);

    $definitions['entity_test']->set('class', RedirectPullTestEntityTest::class);
  }

}

/**
 * Custom entity class for the entity_test entity type.
 */
class RedirectPullTestEntityTest extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add a path field, so the path alias functionality works the same as
    // for nodes.
    $fields['path'] = BaseFieldDefinition::create('path')
      ->setLabel(t('URL alias'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'path',
        'weight' => 30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setComputed(TRUE);

    return $fields;
  }

}

/**
 * Test version of the import processor plugin manager.
 *
 * This adds a processor plugin to mock redirect source URIs.
 */
class RedirectPullTestImportProcessorPluginManager extends ImportProcessorPluginManager {

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();

    // Add our test processor.
    $definitions['redirect_mocker'] = [
      'id' => 'redirect_mocker',
      'class' => TestRedirectSourceMocker::class,
      'provider' => 'entity_share_client',
      'stages' => [
        'prepare_entity_data' => -1000,
      ],
    ];

    return $definitions;
  }

}

/**
 * Import processor which alters incoming JSONAPI data for redirects.
 */
class TestRedirectSourceMocker extends ImportProcessorPluginBase {

  /**
   * Whether to add to the incoming data.
   *
   * @var bool
   */
  public static $addToQuery;

  /**
   * {@inheritdoc}
   */
  public function prepareEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    [$entity_type_id, ] = explode('--', $entity_json_data['type']);
    if ($entity_type_id != 'redirect') {
      return;
    }

    if (static::$addToQuery) {
      // Add something to the redirect source, so that it results in a different
      // redirect hash.
      $entity_json_data['attributes']['redirect_source']['query'] = ['cats' => 'yes'];
    }

  }

}

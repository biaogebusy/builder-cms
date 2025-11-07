<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share\Plugin\jsonapi\FieldEnhancer\EntitySharePathautoEnhancer;
use Drupal\node\NodeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;

/**
 * General functional test class for path field with Pathauto.
 *
 * @group entity_share
 * @group entity_share_client
 */
class PathautoTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_extras',
    'pathauto',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * The Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Service which clears plugin cache.
   *
   * @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface
   */
  protected $pluginCacheClearer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $this->pluginCacheClearer = $this->container->get('plugin.cache_clearer');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test_path_auto' => $this->getCompleteNodeInfos([
            'title' => [
              'value' => 'Automatic',
              'checker_callback' => 'getValue',
            ],
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_path_manual' => $this->getCompleteNodeInfos([
            'title' => [
              'value' => 'Manual',
              'checker_callback' => 'getValue',
            ],
            'path' => [
              'value' => [
                [
                  'alias' => '/manual_path',
                  'pathauto' => 0,
                ],
              ],
              'checker_callback' => 'getValue',
            ],
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test Pathauto resource field enhancer plugin.
   *
   * This test covers all configurations of this plugin.
   */
  public function testPathautoFieldEnhancer() {
    $this->pathautoTestSetup(EntitySharePathautoEnhancer::EXPOSE_CURRENT_PATHAUTO);
    $this->assetEntityPath('es_test_path_auto', '/client/automatic', 'As the pathauto state is preserved, the client website has generated an alias based on its own pathauto pattern.');
    $this->assetEntityPath('es_test_path_manual', '/manual_path', 'As the pathauto state is preserved, the client website has not generated an alias and has used the one provided by the server website.');

    $this->pathautoTestSetup(EntitySharePathautoEnhancer::FORCE_ENABLE_PATHAUTO);
    $this->assetEntityPath('es_test_path_auto', '/client/automatic', 'As the pathauto state is forced to be on, the client website has generated an alias based on its own pathauto pattern.');
    $this->assetEntityPath('es_test_path_manual', '/client/manual', 'As the pathauto state is forced to be on, the client website has generated an alias based on its own pathauto pattern.');

    $this->pathautoTestSetup(EntitySharePathautoEnhancer::FORCE_DISABLE_PATHAUTO);
    $this->assetEntityPath('es_test_path_auto', '/server/automatic', 'As the pathauto state is forced to be off, the client website has not generated an alias and has used the one provided by the server (automatically created on the server website).');
    $this->assetEntityPath('es_test_path_manual', '/manual_path', 'As the pathauto state is forced to be off, the client website has not generated an alias and has used the one provided by the server (manually created on the server website).');
  }

  /**
   * Helper which clears all Pathauto patterns (when they aren't needed).
   */
  protected function deletePathautoPatterns() {
    $pathauto_patterns = $this->entityTypeManager->getStorage('pathauto_pattern')
      ->loadMultiple();
    foreach ($pathauto_patterns as $pathauto_pattern) {
      $pathauto_pattern->delete();
    }
  }

  /**
   * Helper function.
   *
   * @param string $behavior
   *   The behavior of the pathauto field enhancer plugin.
   */
  protected function pathautoTestSetup($behavior) {
    if (empty($this->entityTypeManager->getStorage('jsonapi_resource_config')->load('node--es_test'))) {
      // This is the first run.
      $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
        'id' => 'node--es_test',
        'disabled' => FALSE,
        'path' => 'node/es_test',
        'resourceType' => 'node--es_test',
        'resourceFields' => [
          'path' => [
            'fieldName' => 'path',
            'publicName' => 'path',
            'enhancer' => [
              'id' => 'entity_share_pathauto',
              'settings' => [
                'behavior' => $behavior,
              ],
            ],
            'disabled' => FALSE,
          ],
        ],
      ])->save();
    }
    else {
      // This is not the first run, so certain actions should be done
      // to clean up the data from previous run.
      // 1. Clear "client" pathauto pattern configurations because of
      // the next test.
      $this->deletePathautoPatterns();
      // 2. Reset "remote" response mapping.
      $this->remoteManager->resetResponseMapping();

      // Alter the plugin definition of Path enhancer by changing the behavior.
      $resource_config = $this->configFactory->getEditable('jsonapi_extras.jsonapi_resource_config.node--es_test');
      $resource_fields = $resource_config->get('resourceFields');
      $resource_fields['path']['enhancer']['settings']['behavior'] = $behavior;
      $resource_config->set('resourceFields', $resource_fields)->save();
      $this->pluginCacheClearer->clearCachedDefinitions();
    }

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $pathauto_pattern_storage */
    $pathauto_pattern_storage = $this->entityTypeManager->getStorage('pathauto_pattern');

    /** @var \Drupal\pathauto\PathautoPatternInterface $pattern */
    $pattern = $pathauto_pattern_storage->create([
      'id' => 'server',
      'label' => 'Test',
      'type' => 'canonical_entities:node',
      'pattern' => 'server/[node:title]',
    ]);
    $pattern->save();
    $this->prepareContent();

    // (Re-)import data from JSON:API.
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos['node_es_test_en']['url'];
    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $channel_url);
    $json = Json::decode((string) $response->getBody());

    $this->deleteContent();
    $this->entities = [];
    $this->deletePathautoPatterns();

    /** @var \Drupal\pathauto\PathautoPatternInterface $pattern */
    $pattern = $pathauto_pattern_storage->create([
      'id' => 'client',
      'label' => 'Test',
      'type' => 'canonical_entities:node',
      'pattern' => 'client/[node:title]',
    ]);
    $pattern->save();

    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));
  }

  /**
   * Helper function to test an entity path.
   *
   * @param string $entity_uuid
   *   The entity UUID.
   * @param string $expected_path
   *   The expected path.
   * @param string $message
   *   The message.
   */
  protected function assetEntityPath($entity_uuid, $expected_path, $message = '') {
    $path_auto_node = $this->loadEntity('node', $entity_uuid);
    $path = $path_auto_node->get('path')->getValue();
    $this->assertEquals($expected_path, $path[0]['alias'], $message);
    $path_auto_node->delete();
  }

}

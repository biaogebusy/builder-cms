<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;

/**
 * General functional test class for metatag field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MetatagTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_extras',
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
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
            'title' => [
              'value' => 'Test title',
              'checker_callback' => 'getValue',
            ],
            'field_es_test_metatag' => [
              'value' => serialize([
                'abstract' => 'test abstract',
              ]),
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test the Metatag resource field enhancer plugin.
   *
   * Test it with and without three plugin options.
   */
  public function testMetatagFieldEnhancer() {
    // Initially save JSON:API resource with default Metatag
    // enhancer configuration.
    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_metatag' => [
          'fieldName' => 'field_es_test_metatag',
          'publicName' => 'field_es_test_metatag',
          'enhancer' => [
            'id' => 'entity_share_metatag',
            'settings' => [
              'expose_default_tags' => TRUE,
              'replace_tokens' => FALSE,
              'clear_tokens' => FALSE,
            ],
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    $this->prepareContent();

    // Import data from JSON:API.
    $this->importData();

    // Load and remember the metatags of newly imported node.
    $node = $this->loadEntity('node', 'es_test');
    $node_metatags = unserialize($node->get('field_es_test_metatag')->getValue()[0]['value']);

    // In this case even if default metatags are exposed, as the exposed data
    // is only token, it is not saved back into the field.
    $expected_metatags = [
      'abstract' => 'test abstract',
    ];
    // This node must be deleted because of next import.
    $node->delete();

    $this->assertEquals($expected_metatags, $node_metatags, 'The node has the expected metatags.');

    // Reset the responses in TestRemoteManager::doRequest because the
    // response is supposed to change on "remote".
    $this->remoteManager->resetResponseMapping();
    // Generate "remote" content again.
    $this->prepareContent();

    // Load and remember the metatags of newly generated "remote" node.
    $node = $this->loadEntity('node', 'es_test');
    $node_title = $node->label();
    $node_url = $node->toUrl('canonical')->setAbsolute()->toString();

    // In this case, default tags with tokens had been replaced by the real
    // values. But as for the first case, when a value is only an unreplaced
    // token, Metatag does not save back the value.
    // So for example, we don't see in the result the [node:summary] token.
    $expected_metatags = [
      'canonical_url' => $node_url,
      'title' => $node_title . ' | Drupal',
      'abstract' => 'test abstract',
    ];

    // Alter the plugin definition of Metatag enhancer:
    // activate "Replace tokens" option.
    $resource_config = $this->configFactory->getEditable('jsonapi_extras.jsonapi_resource_config.node--es_test');
    $resource_fields = $resource_config->get('resourceFields');
    $resource_fields['field_es_test_metatag']['enhancer']['settings']['replace_tokens'] = TRUE;
    $resource_config->set('resourceFields', $resource_fields);
    $resource_config->save();
    $this->pluginCacheClearer->clearCachedDefinitions();

    // Re-import data from JSON:API.
    $this->importData();

    $node = $this->loadEntity('node', 'es_test');
    $node_metatags = unserialize($node->get('field_es_test_metatag')->getValue()[0]['value']);
    // This node must be deleted because of next import.
    $node->delete();

    $this->assertEquals($expected_metatags, $node_metatags, 'The node has the expected metatags.');

    // Reset the responses in TestRemoteManager::doRequest because the
    // response is supposed to change on "remote".
    $this->remoteManager->resetResponseMapping();
    // Generate "remote" content again.
    $this->prepareContent();

    // Load and remember the metatags of newly generated "remote" node.
    $node = $this->loadEntity('node', 'es_test');
    $node_title = $node->label();
    $node_url = $node->toUrl('canonical')->setAbsolute()->toString();

    // Same as the second case, the difference will be in the JSON output, the
    // [node:summary] token will be exposed but not replaced, so Metatag does
    // not save back the value.
    $expected_metatags = [
      'canonical_url' => $node_url,
      'title' => $node_title . ' | Drupal',
      'abstract' => 'test abstract',
    ];

    // Alter the plugin definition of Metatag enhancer:
    // activate "Clear tokens" option (ie. all settings checked).
    $resource_config = $this->configFactory->getEditable('jsonapi_extras.jsonapi_resource_config.node--es_test');
    $resource_fields = $resource_config->get('resourceFields');
    $resource_fields['field_es_test_metatag']['enhancer']['settings']['clear_tokens'] = TRUE;
    $resource_config->set('resourceFields', $resource_fields);
    $resource_config->save();
    $this->pluginCacheClearer->clearCachedDefinitions();

    // Re-import data from JSON:API.
    $this->importData();

    $node = $this->loadEntity('node', 'es_test');
    $node_metatags = unserialize($node->get('field_es_test_metatag')->getValue()[0]['value']);

    $this->assertEquals($expected_metatags, $node_metatags, 'The node has the expected metatags.');
  }

  /**
   * Import data from remote and clear the content.
   */
  protected function importData() {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos['node_es_test_en']['url'];
    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $channel_url);
    $json = Json::decode((string) $response->getBody());

    $this->deleteContent();
    $this->entities = [];

    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));
  }

}

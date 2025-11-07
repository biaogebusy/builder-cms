<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Url;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Drupal\entity_share_test\EntityFieldHelperTrait;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\entity_share_server\Functional\EntityShareServerRequestTestTrait;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\user\UserInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * Base class for Entity Share Client functional tests.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
abstract class EntityShareClientFunctionalTestBase extends BrowserTestBase {

  use RandomGeneratorTrait;
  use EntityShareServerRequestTestTrait;
  use EntityFieldHelperTrait;

  /**
   * The import config ID.
   */
  const IMPORT_CONFIG_ID = 'test_import_config';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'basic_auth',
    'entity_share_client',
    'entity_share_client_remote_manager_test',
    'entity_share_server',
    'entity_share_test',
    'jsonapi_extras',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The tested entity type.
   *
   * @var string
   */
  protected static $entityTypeId = NULL;

  /**
   * The tested entity type bundle.
   *
   * @var string
   */
  protected static $entityBundleId = NULL;

  /**
   * The tested entity langcode.
   *
   * @var string
   */
  protected static $entityLangcode = NULL;

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A test user with access to the channel list.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $channelUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The visited URLs during setup.
   *
   * Prevents infinite loop during preparation of website emulation.
   *
   * @var string[]
   */
  protected $visitedUrlsDuringSetup = [];

  /**
   * The remote used for the test.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * The channels used for the test.
   *
   * @var \Drupal\entity_share_server\Entity\ChannelInterface[]
   */
  protected $channels = [];

  /**
   * The import config used for the test.
   *
   * @var \Drupal\entity_share_client\Entity\ImportConfigInterface
   */
  protected $importConfig;

  /**
   * A mapping of the entities created for the test.
   *
   * With the following structure:
   * [
   *   'entityTypeId' => [
   *     Entity object,
   *   ],
   * ]
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[][]
   */
  protected $entities = [];

  /**
   * A mapping of the entity data used for the test.
   *
   * @var array
   */
  protected $entitiesData;

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * The client authorization manager service.
   *
   * @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginManager
   */
  protected $authPluginManager;

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Prepare users.
    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->channelUser = $this->drupalCreateUser($this->getChannelUserPermissions());

    // Enable count meta to be able to use the importChannel method on the
    // import service.
    $config = $this->container->get('config.factory')->getEditable('jsonapi_extras.settings');
    $config->set('include_count', TRUE);
    $config->save(TRUE);

    // Retrieve required services.
    $this->fileSystem = $this->container->get('file_system');
    $this->streamWrapperManager = $this->container->get('stream_wrapper_manager');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityDefinitions = $this->entityTypeManager->getDefinitions();
    $this->importService = $this->container->get('entity_share_client.import_service');
    $this->remoteManager = $this->container->get('entity_share_client.remote_manager');
    $this->authPluginManager = $this->container->get('plugin.manager.entity_share_client_authorization');
    $this->keyValueStore = $this->container->get('keyvalue')->get(ClientAuthorizationInterface::LOCAL_STORAGE_KEY_VALUE_COLLECTION);
    $this->moduleExtensionList = $this->container->get('extension.list.module');
    $this->fileUrlGenerator = $this->container->get('file_url_generator');

    $this->createRemote($this->channelUser);
    $this->createChannel($this->channelUser);
    $this->createImportConfig();
  }

  /**
   * Helper function.
   *
   * Need to separate those steps from the setup in the base class, because some
   * sub-class setup may change the content of the fixture.
   */
  protected function postSetupFixture() {
    $this->prepareContent();
    $this->populateRequestService();
    $this->deleteContent();
  }

  /**
   * Gets the permissions for the admin user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getAdministratorPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'administer_channel_entity',
    ];
  }

  /**
   * Gets the permissions for the channel user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getChannelUserPermissions() {
    return [
      ChannelInterface::CHANNELS_ACCESS_PERMISSION,
    ];
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to authenticate with.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions(AccountInterface $account) {
    return [
      RequestOptions::HEADERS => [
        'Authorization' => 'Basic ' . base64_encode($account->getAccountName() . ':' . $account->passRaw),
      ],
    ];
  }

  /**
   * Helper function to create the remote that point to the site itself.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose credentials will be used for the remote.
   */
  protected function createRemote(UserInterface $user) {
    $remote_storage = $this->entityTypeManager->getStorage('remote');
    $remote = $remote_storage->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'url' => $this->buildUrl('<front>'),
    ]);
    $plugin = $this->createAuthenticationPlugin($user, $remote);
    $remote->mergePluginConfig($plugin);
    // Save the "Remote" config entity.
    $remote->save();
    $this->remote = $remote;
  }

  /**
   * Helper function to create the authentication plugin.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose credentials will be used for the plugin.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The "Remote" entity.
   *
   * @return \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface
   *   The Entity share Authorization plugin.
   */
  protected function createAuthenticationPlugin(UserInterface $user, RemoteInterface $remote) {
    // By default, create "Basic Auth" plugin for authorization.
    $plugin = $this->authPluginManager->createInstance('basic_auth');
    $configuration = $plugin->getConfiguration();
    $credentials = [
      'username' => $user->getAccountName(),
      'password' => $user->passRaw,
    ];
    // We are using key value store for local credentials storage.
    $storage_key = $configuration['uuid'];
    $this->keyValueStore->set($storage_key, $credentials);
    $configuration['data'] = [
      'credential_provider' => 'entity_share',
      'storage_key' => $storage_key,
    ];
    $plugin->setConfiguration($configuration);
    return $plugin;
  }

  /**
   * Helper function to create the channel used for the test.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user which credential will be used for the remote.
   */
  protected function createChannel(UserInterface $user) {
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode,
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => static::$entityTypeId,
      'channel_bundle' => static::$entityBundleId,
      'channel_langcode' => static::$entityLangcode,
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * Helper function to create the import config used for the test.
   */
  protected function createImportConfig() {
    $import_config_storage = $this->entityTypeManager->getStorage('import_config');
    $import_config = $import_config_storage->create([
      'id' => $this::IMPORT_CONFIG_ID,
      'label' => $this->randomString(),
      'import_maxsize' => 50,
      'import_processor_settings' => $this->getImportConfigProcessorSettings(),
    ]);
    $import_config->save();
    $this->importConfig = $import_config;
  }

  /**
   * Helper function to add/modify plugins in import config, runtime.
   *
   * @param array $plugins
   *   Plugin configurations.
   *   For format @see getImportConfigProcessorSettings().
   */
  protected function mergePluginsToImportConfig(array $plugins) {
    $processor_settings = $this->importConfig->get('import_processor_settings');
    // Add new plugins or override existing plugin configurations.
    $processor_settings = NestedArray::mergeDeepArray([
      $processor_settings,
      $plugins,
    ]);
    $this->importConfig->set('import_processor_settings', $processor_settings);
    $this->importConfig->save();
  }

  /**
   * Helper function to remove a plugin from import config, runtime.
   *
   * @param string $plugin_id
   *   The identifier of import plugin.
   */
  protected function removePluginFromImportConfig(string $plugin_id) {
    $processor_settings = $this->importConfig->get('import_processor_settings');
    if (isset($processor_settings[$plugin_id])) {
      unset($processor_settings[$plugin_id]);
      $this->importConfig->set('import_processor_settings', $processor_settings);
      $this->importConfig->save();
    }
  }

  /**
   * Helper function to create the import config used for the test.
   *
   * @return array
   *   The import processors config.
   */
  protected function getImportConfigProcessorSettings() {
    // Only locked import processors are enabled by default.
    return [
      'default_data_processor' => [
        'policy' => EntityImportStatusInterface::IMPORT_POLICY_DEFAULT,
        'update_policy' => FALSE,
        'weights' => [
          'is_entity_importable' => -10,
          'post_entity_save' => 0,
          'prepare_importable_entity_data' => -100,
        ],
      ],
      'entity_reference' => [
        'max_recursion_depth' => -1,
        'weights' => [
          'process_entity' => 10,
        ],
      ],
    ];
  }

  /**
   * Helper function to create the content required for the tests.
   */
  protected function prepareContent() {
    $entities_data = $this->getEntitiesData();

    foreach ($entities_data as $entity_type_id => $data_per_languages) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      if (!isset($this->entities[$entity_type_id])) {
        $this->entities[$entity_type_id] = [];
      }

      foreach ($data_per_languages as $langcode => $entity_data) {
        foreach ($entity_data as $entity_uuid => $entity_data_per_field) {
          // If the entity has already been created, create a translation.
          if (isset($this->entities[$entity_type_id][$entity_uuid])) {
            $prepared_entity_data = $this->prepareEntityData($entity_data_per_field);
            $entity = $this->entities[$entity_type_id][$entity_uuid];
            $entity->addTranslation($langcode, $prepared_entity_data);
            $entity->save();
          }
          else {
            $entity_data_per_field += [
              'langcode' => [
                'value' => $langcode,
                'checker_callback' => 'getValue',
              ],
              'uuid' => [
                'value' => $entity_uuid,
                'checker_callback' => 'getValue',
              ],
            ];
            $prepared_entity_data = $this->prepareEntityData($entity_data_per_field);

            $entity = $entity_storage->create($prepared_entity_data);
            $entity->save();
          }

          $this->entities[$entity_type_id][$entity_uuid] = $entity;
        }
      }
    }
  }

  /**
   * Helper function to prepare entity data.
   *
   * Get an array usable to create entity or translation.
   *
   * @param array $entityData
   *   The entity data as in getEntitiesData().
   *
   * @return array
   *   The array of prepared values.
   */
  protected function prepareEntityData(array $entityData) {
    $prepared_entity_data = [];

    foreach ($entityData as $field_machine_name => $data) {
      // Some data are dynamic.
      if (isset($data['value_callback'])) {
        $prepared_entity_data[$field_machine_name] = call_user_func($data['value_callback']);
      }
      else {
        $prepared_entity_data[$field_machine_name] = $data['value'];
      }
    }

    return $prepared_entity_data;
  }

  /**
   * Helper function to get a mapping of the entities data.
   *
   * Used to create the entities for the test and to test that it has been
   * recreated properly.
   */
  abstract protected function getEntitiesDataArray();

  /**
   * Helper function to get a mapping of the entities data.
   *
   * Used to create the entities for the test and to test that it has been
   * recreated properly.
   */
  protected function getEntitiesData() {
    if (!isset($this->entitiesData)) {
      $this->entitiesData = $this->getEntitiesDataArray();
    }

    return $this->entitiesData;
  }

  /**
   * Helper function to populate the request service with responses.
   */
  protected function populateRequestService() {
    // Do not use RemoteManager::getChannelsInfos so we are able to test
    // behavior with website in subdirectory on testbot.
    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');

    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $entity_share_entrypoint_url->setAbsolute()->toString());
    $json_response = Json::decode((string) $response->getBody());

    foreach ($json_response['data']['channels'] as $channel_data) {
      $this->discoverJsonApiEndpoints($channel_data['url']);
      $this->discoverJsonApiEndpoints($channel_data['url_uuid']);
    }
  }

  /**
   * Helper function to populate the request service with responses.
   *
   * @param string $url
   *   The url to request.
   */
  protected function discoverJsonApiEndpoints($url) {
    // Prevents infinite loop.
    if (in_array($url, $this->visitedUrlsDuringSetup)) {
      return;
    }
    $this->visitedUrlsDuringSetup[] = $url;

    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $url);
    $json_response = Json::decode((string) $response->getBody());

    // Loop on the data and relationships to get expected endpoints.
    if (is_array($json_response['data'])) {
      foreach (EntityShareUtility::prepareData($json_response['data']) as $data) {
        if (isset($data['relationships'])) {
          foreach ($data['relationships'] as $field_data) {
            // Do not check related endpoints if there are no referenced
            // entities.
            if ($field_data['data'] == NULL || empty($field_data['data'])) {
              continue;
            }

            // Do not check related endpoints for config entities or users.
            $prepared_field_data = EntityShareUtility::prepareData($field_data['data']);
            $config_or_user = FALSE;
            foreach ($prepared_field_data as $field_data_value) {
              $parsed_type = explode('--', $field_data_value['type']);
              $entity_type_id = $parsed_type[0];
              if ($entity_type_id == 'user') {
                $config_or_user = TRUE;
                break;
              }
              elseif ($this->entityDefinitions[$entity_type_id]->getGroup() == 'configuration') {
                $config_or_user = TRUE;
                break;
              }
            }
            if ($config_or_user) {
              continue;
            }

            if (isset($field_data['links']['related']['href'])) {
              $this->discoverJsonApiEndpoints($field_data['links']['related']['href']);
            }
          }
        }

        // File entity.
        if ($data['type'] == 'file--file' && isset($data['attributes']['uri']['url'])) {
          // Need to handle exception for the test where the physical file has
          // been deleted.
          try {
            $this->remoteManager->request($this->remote, 'GET', $data['attributes']['uri']['url']);
          }
          catch (ClientException $exception) {
            // Do nothing.
          }
        }
      }
    }

    // Handle pagination.
    if (isset($json_response['links']['next']['href'])) {
      $this->discoverJsonApiEndpoints($json_response['links']['next']['href']);
    }
  }

  /**
   * Helper function to delete the prepared content.
   */
  protected function deleteContent() {
    foreach ($this->entities as $entity_type_id => $entity_list) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      foreach ($entity_list as $entity_uuid => $entity) {
        $entity->delete();

        // Check that the entity has been deleted.
        $remaining_entities = $entity_storage->loadByProperties(['uuid' => $entity_uuid]);
        $this->assertTrue(empty($remaining_entities), 'The ' . $entity_type_id . ' with UUID ' . $entity_uuid . ' has been deleted.');
      }
    }
  }

  /**
   * Helper function to delete all (prepared or imported) content.
   *
   * This function doesn't assert the deletion of entities.
   */
  protected function resetImportedContent() {
    $entity_type_ids = array_keys($this->getEntitiesDataArray());
    foreach ($entity_type_ids as $entity_type_id) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entities = $entity_storage->loadByProperties();
      if ($entities) {
        foreach ($entities as $entity) {
          $entity->delete();
        }
      }
    }
    $this->entities = [];
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
  }

  /**
   * Helper function that test that the entities had been recreated.
   */
  protected function checkCreatedEntities() {
    $entities_data = $this->getEntitiesData();

    foreach ($entities_data as $entity_type_id => $data_per_languages) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      foreach ($data_per_languages as $language_id => $entity_data) {
        foreach ($entity_data as $entity_uuid => $entity_data_per_field) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface[] $recreated_entities */
          $recreated_entities = $entity_storage->loadByProperties(['uuid' => $entity_uuid]);

          // Check that the entity has been recreated.
          $this->assertTrue(!empty($recreated_entities), 'The ' . $entity_type_id . ' with UUID ' . $entity_uuid . ' has been recreated.');

          // Check the values.
          if (!empty($recreated_entities)) {
            $recreated_entity = array_shift($recreated_entities);

            $entity_translation = $recreated_entity->getTranslation($language_id);

            foreach ($entity_data_per_field as $field_machine_name => $data) {
              // Some data are dynamic.
              if (isset($data['value_callback'])) {
                $data['value'] = call_user_func($data['value_callback']);
              }

              // When additional keys in field data are created by Drupal. We
              // need to filter this structure.
              if ($data['checker_callback'] == 'getFilteredStructureValues') {
                // Assume that also for single value fields, the data will be
                // set using an array of values.
                $structure = array_keys($data['value'][0]);
                $this->assertEquals($data['value'], $this->getFilteredStructureValues($entity_translation, $field_machine_name, $structure), 'The data of the field ' . $field_machine_name . ' has been recreated.');
              }
              else {
                $this->assertEquals($data['value'], $this->{$data['checker_callback']}($entity_translation, $field_machine_name), 'The data of the field ' . $field_machine_name . ' has been recreated.');
              }
            }
          }
        }
      }
    }
  }

  /**
   * Helper function to import all channels.
   */
  protected function pullEveryChannels() {
    foreach (array_keys($this->channels) as $channel_id) {
      $this->pullChannel($channel_id);
    }
  }

  /**
   * Helper function to import one channel.
   *
   * @param string $channel_id
   *   The channel ID.
   */
  protected function pullChannel($channel_id) {
    $import_context = new ImportContext($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID);
    $this->importService->importChannel($import_context);
    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    batch_process();
  }

  /**
   * Helper function.
   *
   * Imports selected entities.
   *
   * @param string[] $selected_entities
   *   Array of entity UUIDs.
   * @param string $channel_id
   *   Identifier of the import channel.
   */
  protected function importSelectedEntities(array $selected_entities, string $channel_id = '') {
    // Generate the remote URL.
    // Unless overridden by parameter, pull from the default channel.
    $channel_id = !empty($channel_id) ? $channel_id : static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode;
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, $channel_id);
    // Prepare import context.
    $import_context = new ImportContext($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    // Imports data from the remote URL.
    $this->importService->importFromUrl($prepared_url);
  }

  /**
   * Helper function to get the JSON:API data of an entity.
   *
   * @param string $channel_id
   *   The channel ID.
   * @param string $entity_uuid
   *   The entity UUID.
   *
   * @return array
   *   An array of decoded data.
   */
  protected function getEntityJsonData($channel_id, $entity_uuid) {
    $json_data = [];
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);

    $channel_url = $channel_infos[$channel_id]['url'];
    while ($channel_url) {
      $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $channel_url);
      $json = Json::decode((string) $response->getBody());

      $json_data = EntityShareUtility::prepareData($json['data']);

      foreach ($json_data as $entity_json_data) {
        if ($entity_json_data['id'] == $entity_uuid) {
          return $entity_json_data;
        }
      }

      if (isset($json['links']['next']['href'])) {
        $channel_url = $json['links']['next']['href'];
      }
      else {
        $channel_url = FALSE;
      }
    }

    return $json_data;
  }

  /**
   * Helper function.
   *
   * @param array $media_infos
   *   The media infos to use.
   *
   * @return array
   *   Return common part to create medias.
   */
  protected function getCompleteMediaInfos(array $media_infos) {
    return array_merge([
      'status' => [
        'value' => NodeInterface::PUBLISHED,
        'checker_callback' => 'getValue',
      ],
    ], $media_infos);
  }

  /**
   * Helper function.
   *
   * @param array $node_infos
   *   The node infos to use.
   *
   * @return array
   *   Return common part to create nodes.
   */
  protected function getCompleteNodeInfos(array $node_infos) {
    return array_merge([
      'type' => [
        'value' => static::$entityBundleId,
        'checker_callback' => 'getTargetId',
      ],
      'title' => [
        'value' => $this->randomString(),
        'checker_callback' => 'getValue',
      ],
    ], $node_infos);
  }

  /**
   * Helper function.
   *
   * @param array $taxonomy_term_infos
   *   The taxonomy term infos to use.
   *
   * @return array
   *   Return common part to create taxonomy terms.
   */
  protected function getCompleteTaxonomyTermInfos(array $taxonomy_term_infos) {
    return array_merge([
      'vid' => [
        'value' => static::$entityBundleId,
        'checker_callback' => 'getTargetId',
      ],
      'name' => [
        'value' => $this->randomString(),
        'checker_callback' => 'getValue',
      ],
    ], $taxonomy_term_infos);
  }

  /**
   * Helper function.
   *
   * @param array $paragraph_infos
   *   The paragraph infos to use.
   *
   * @return array
   *   Return common part to create paragraph.
   */
  protected function getCompleteParagraphInfos(array $paragraph_infos) {
    return array_merge([
      'type' => [
        'value' => 'es_test',
        'checker_callback' => 'getTargetId',
      ],
    ], $paragraph_infos);
  }

  /**
   * Helper function.
   *
   * @param array $block_infos
   *   The block infos to use.
   *
   * @return array
   *   Return common part to create blocks.
   */
  protected function getCompleteBlockInfos(array $block_infos) {
    return array_merge([
      'type' => [
        'value' => 'es_test',
        'checker_callback' => 'getTargetId',
      ],
      'info' => [
        'value' => $this->randomString(),
        'checker_callback' => 'getValue',
      ],
    ], $block_infos);
  }

  /**
   * Helper function.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_uuid
   *   Then entity UUID.
   *
   * @return string
   *   Return the entity ID if it exists. Empty string otherwise.
   */
  protected function getEntityId($entity_type_id, $entity_uuid) {
    $existing_entity_id = '';
    $existing_entity = $this->loadEntity($entity_type_id, $entity_uuid);

    if (!is_null($existing_entity)) {
      $existing_entity_id = $existing_entity->id();
    }

    return $existing_entity_id;
  }

  /**
   * Helper function.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_uuid
   *   Then entity UUID.
   *
   * @return string
   *   Return the entity revision ID if it exists. Empty string otherwise.
   */
  protected function getEntityRevisionId($entity_type_id, $entity_uuid) {
    $existing_entity_id = '';
    $existing_entity = $this->loadEntity($entity_type_id, $entity_uuid);

    if (!is_null($existing_entity) && $existing_entity instanceof RevisionableInterface) {
      $existing_entity_id = $existing_entity->getRevisionId();
    }

    return $existing_entity_id;
  }

  /**
   * Helper function.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_uuid
   *   The entity UUID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if it exists. NULL otherwise.
   */
  protected function loadEntity($entity_type_id, $entity_uuid) {
    $existing_entity = NULL;
    $existing_entities = $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties([
      'uuid' => $entity_uuid,
    ]);

    if (!empty($existing_entities)) {
      $existing_entity = array_shift($existing_entities);
    }

    return $existing_entity;
  }

  /**
   * Helper function.
   *
   * @param string $entity_uuid
   *   The entity UUID.
   * @param string $remote_entity_langcode
   *   The entity langcode.
   *
   * @return \Drupal\entity_share_client\Entity\EntityImportStatusInterface|null
   *   Return the entity if it exists. NULL otherwise.
   */
  protected function loadImportStatusEntity($entity_uuid, $remote_entity_langcode) {
    $existing_entity = NULL;
    $existing_entities = $this->entityTypeManager->getStorage('entity_import_status')->loadByProperties([
      'entity_uuid' => $entity_uuid,
      'langcode' => $remote_entity_langcode,
    ]);

    if (!empty($existing_entities)) {
      $existing_entity = array_shift($existing_entities);
    }

    return $existing_entity;
  }

  /**
   * Helper function.
   *
   * @param array $selected_entities
   *   An array of entities UUIDs to filter the endpoint by.
   * @param string $channel_id
   *   The channel id.
   *
   * @return string
   *   The prepared URL.
   */
  protected function prepareUrlFilteredOnUuids(array $selected_entities, $channel_id) {
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos[$channel_id]['url'];
    return EntityShareUtility::prepareUuidsFilteredUrl($channel_url, array_values($selected_entities));
  }

  /**
   * Helper function.
   *
   * @return array
   *   An array of data.
   */
  protected function preparePhysicalFilesAndFileEntitiesData() {
    $files_entities_data = [];
    foreach (static::$filesData as $file_uuid => $file_data) {
      $stream_wrapper = $this->streamWrapperManager->getViaUri($file_data['uri']);
      $directory_uri = $stream_wrapper->dirname($file_data['uri']);
      $this->fileSystem->prepareDirectory($directory_uri, FileSystemInterface::CREATE_DIRECTORY);
      if (isset($file_data['file_content'])) {
        file_put_contents($file_data['uri'], $file_data['file_content']);
        $this->filesSize[$file_uuid] = filesize($file_data['uri']);
      }
      elseif (isset($file_data['file_content_callback'])) {
        $this->{$file_data['file_content_callback']}($file_uuid, $file_data);
      }

      $files_entities_data[$file_uuid] = [
        'filename' => [
          'value' => $file_data['filename'],
          'checker_callback' => 'getValue',
        ],
        'uri' => [
          'value' => $file_data['uri'],
          'checker_callback' => 'getValue',
        ],
        'filemime' => [
          'value' => $file_data['filemime'],
          'checker_callback' => 'getValue',
        ],
        'status' => [
          'value' => FileInterface::STATUS_PERMANENT,
          'checker_callback' => 'getValue',
        ],
      ];
    }
    return $files_entities_data;
  }

  /**
   * Common parts between FileTest and MediaEntityReferenceTest classes.
   *
   * Common parts in testBasicPull() method to avoid code duplication.
   */
  protected function commonBasicPull() {
    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been deleted.');
    }

    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    foreach (static::$filesData as $file_uuid => $file_data) {
      $this->assertTrue(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been pulled and recreated.');
      if (isset($file_data['file_content'])) {
        $recreated_file_data = file_get_contents($file_data['uri']);
        $this->assertEquals($file_data['file_content'], $recreated_file_data, 'The recreated physical file ' . $file_data['filename'] . ' has the same content.');
      }

      if (isset($this->filesSize[$file_uuid])) {
        $this->assertEquals($this->filesSize[$file_uuid], filesize($file_data['uri']), 'The recreated physical file ' . $file_data['filename'] . ' has the same size as the original.');
      }
    }
  }

  /**
   * Helper function.
   *
   * @param string $file_uuid
   *   The file UUID.
   * @param array $file_data
   *   The file data as in static::filesData.
   */
  protected function getMediaEntityReferenceTestFiles($file_uuid, array $file_data) {
    $filepath = $this->moduleExtensionList->getPath('entity_share') . '/tests/fixtures/files/' . $file_data['filename'];
    $this->fileSystem->copy($filepath, PublicStream::basePath());
    $this->filesSize[$file_uuid] = filesize($filepath);
  }

  /**
   * Helper function: unsets remote manager's cached data.
   *
   * This is needed because our remote ID is not changing, and remote manager
   * caches certain values based on the remote ID.
   * Another solution would be to reinitialize $this->remoteManager and create
   * new remote.
   */
  protected function resetRemoteCaches() {
    $this->remoteManager->resetRemoteInfos();
    $this->remoteManager->resetHttpClientsCache('json_api');
    // Reset "remote" response mapping (ie. cached JSON:API responses).
    $this->remoteManager->resetResponseMapping();
  }

}

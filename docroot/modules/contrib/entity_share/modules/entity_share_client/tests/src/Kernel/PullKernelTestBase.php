<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\entity_share_client\Http\ClientFactoryInterface;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Serialization\Json;
use Drupal\entity_share_client\Service\RemoteManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\EntityTrait;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Base class for Kernel tests which simulate pulling entities.
 *
 * This uses a mocked Guzzle client to either mock responses to Entity Share
 * Client's requests, or direct them into the test site's HTTP kernel.
 *
 * Source entities are defined in static::getEntitiesData(), and use fixed
 * rather than generated UUIDs.
 *
 * Pulled entities have their UUIDs prefixed in the mocked JSONAPI data, which
 * allows the same site to have source and pulled entities stored
 * simultaneously. The lifecycle of an entity is thus:
 * 1. Create an entity in the test site, with UUID 'foo'.
 * 2. Pull the entity. The mocked Guzzle client changes the UUID in the JSON
 *    data to 'PULLED-foo'.
 * 3. The pulled entity with UUID 'PULLED-foo' is now in the test site. Given
 *    the source entity we can find the pulled entity, and vice versa. The two
 *    entities can have their values compared.
 * 4. To test updates on the server, update entity 'foo', and pull entity
 *    'PULLED-foo'.
 *
 * @group entity_share
 * @group entity_share_client
 */
abstract class PullKernelTestBase extends KernelTestBase implements ServiceModifierInterface, ClientFactoryInterface {
  use UserCreationTrait;
  use EntityTrait;

  /**
   * The prefix added to mocked UUIDs for pulled entities.
   */
  const MOCKED_UUID_PREFIX = 'PULLED-';

  /**
   * Value for import configuration to use default weights from a plugin.
   *
   * @see self::getImportConfigProcessorSettings()
   */
  const PLUGIN_DEFINITION_STAGES = TRUE;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity repository service.
   *
   * @var Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The remote used for the test.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * The import config used for the test.
   *
   * @var \Drupal\entity_share_client\Entity\ImportConfigInterface
   */
  protected $importConfig;

  /**
   * The channels used for the test.
   *
   * @var \Drupal\entity_share_server\Entity\ChannelInterface[]
   */
  protected $channels = [];

  /**
   * A test user with access to the channel list.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $channelUser;

  /**
   * An array of field types which are considered reference fields.
   *
   * Test classes which use other reference field types should override this.
   *
   * @var array
   */
  protected $referenceFieldTypes = ['entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'serialization',
    'file',
    'jsonapi',
    'entity_share',
    'entity_share_client',
    'entity_share_server',
  ];

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->entityRepository = $this->container->get('entity.repository');

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_import_status');
    $this->installConfig(['field']);

    $this->createRemote();
    $this->createImportConfig();

    // We need the current user to have access to channels so that our request
    // to the HTTP kernel in our mocked Guzzle client allows access.
    $this->channelUser = $this->createUser([
      ChannelInterface::CHANNELS_ACCESS_PERMISSION,
    ]);
    $this->setCurrentUser($this->channelUser);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Switch the http_client_factory service to this class.
    // @see static::fromOptions()
    $service_definition = $container->getDefinition('http_client_factory');
    $service_definition->setClass(static::class);

    // Alter the remote manager to make it throw exceptions on JSONAPI requests.
    $service_definition = $container->getDefinition('entity_share_client.remote_manager');
    $service_definition->setClass(KernelTestRemoteManager::class);
  }

  /**
   * {@inheritdoc}
   */
  public function fromOptions(array $config = []) {
    // Return a Guzzle client with a mocked handler.
    $mocked_client = new Client([
      'handler' => static::class . '::guzzleHandler',
    ]);

    return $mocked_client;
  }

  /**
   * Mocked Guzzle handler callback.
   *
   * This allows us to intercept the requests that the Entity Share client
   * makes, and return data based on local entities which we consider to be
   * the source entities.
   *
   * The following types of request come in from the client:
   * - The /entity_share custom endpoint, which returns data on channels.
   * - The JSONAPI endpoint for an entity bundle, with a 'changed' query to get
   *   recent entities.
   * - The JSONAPI endpoint for an entity bundle.
   * - The JSONAPI endpoint for a single entity.
   *
   * This needs to be a static method rather than a closure, because making
   * assertions inside a closure causes a PHP error on some environments.
   */
  static public function guzzleHandler(GuzzleRequest $request) {
    $path = $request->getUri()->getPath();
    $uri  = (string) $request->getUri();

    // The RemoteManager first makes a request to get data on all
    // channels. It's simplest to just pass this through to the HTTP
    // kernel which lets the Entity Share Server's controller handle it.
    if ($path == 'entity_share') {
      // Can't use the Symfony PSR-7 Bridge to convert, as the Guzzle
      // request is an outgoing request, and the HTTP kernel wants an
      // incoming request, and these have different interfaces in PSR-7.
      $symfony_request = Request::create('/entity_share');
      $symfony_request->headers->set('Accept', 'application/json');

      // The container property is not set here apparently!
      $http_kernel = \Drupal::service('http_kernel');
      $response = $http_kernel->handle($symfony_request);

      Assert::assertEquals(Response::HTTP_OK, $response->getStatusCode());
      $content = $response->getContent();

      // Sanity check the data on channels.
      $data = Json::decode($content);
      Assert::assertNotEmpty($data['data']['channels']);

      // Convert the Symfony response to a Guzzle response.
      $psr17_factory = new \GuzzleHttp\Psr7\HttpFactory();
      $psr_http_factory = new PsrHttpFactory($psr17_factory, $psr17_factory, $psr17_factory, $psr17_factory);
      $guzzle_response = $psr_http_factory->createResponse($response);

      return $guzzle_response;
    }

    $query = \GuzzleHttp\Psr7\Query::parse($request->getUri()->getQuery());

    // The RemoteManager queries the JSONAPI resource to get an entity
    // count. Simplest thing here is to return just the data it expects.
    // This seems to be a valid way to detect a count request rather than
    // a request for content data, but HOW?? does that get into the URL
    // in the first place???
    if (in_array('changed', $query)) {
      $data = [
        'meta' => [
          // @todo We should get this value from the test class's list
          // of entity data. Alternatively, maybe it doesn't matter what
          // we return here?
          'count' => 1,
        ],
      ];

      return new GuzzleResponse(200, [], Json::encode($data));
    }

    // Any other request is for a JSONAPI resource, either a listing or a
    // single entity.
    // Get the path pieces. We need to trim the path, as otherwise the first
    // piece is an empty string.
    $path_pieces = explode('/', trim($path, '/'));

    // A repeat request will use the mocked UUID, so convert the UUID to
    // the source UUID.
    if (isset($path_pieces[5]) && str_starts_with($path_pieces[5], static::MOCKED_UUID_PREFIX)) {
      $path = str_replace(static::MOCKED_UUID_PREFIX, '', $path);
      $uri  = str_replace(static::MOCKED_UUID_PREFIX, '', $uri);
    }

    // Pass in the string URI because the $query array obtained from Guzzle
    // doesn't seem immediately compatible with the query expected from Symfony.
    // @todo Clean this up.
    $request = Request::create($uri, 'GET');
    $request->headers->set('Accept', 'application/json');

    $http_kernel = \Drupal::service('http_kernel');
    $response = $http_kernel->handle($request);

    Assert::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $content = $response->getContent();
    $data = Json::decode($content);

    // Detect whether this is a listing or a single entity request.
    if (count($path_pieces) == 3) {
      // A listing path is of the form jsonapi/ENTITY_TYPE/BUNDLE.
      // Mock the entity data:
      // - Filter out pulled entities
      // - Replace entity UUIDs with mocked ones.
      $filtered_entity_data = [];
      foreach ($data['data'] as $entity_data) {
        if (str_starts_with($entity_data['id'], static::MOCKED_UUID_PREFIX)) {
          continue;
        }

        // Mock the incoming UUIDs.
        $old = $entity_data['id'];
        static::mockJsonApiUuids($entity_data);

        Assert::assertNotEquals($old, $entity_data['id']);

        // Add the mocked entity data to a new array so its numeric indexing is
        // in sequence.
        $filtered_entity_data[] = $entity_data;
      }

      $data['data'] = $filtered_entity_data;
    }
    elseif (count($path_pieces) == 4) {
      // A single entity is of the form jsonapi/ENTITY_TYPE/BUNDLE/UUID.
      $entity_data =& $data['data'];

      $old = $entity_data['id'];

      // Mock the incoming UUIDs.
      static::mockJsonApiUuids($entity_data);
      Assert::assertNotEquals($old, $entity_data['id']);
    }
    elseif (count($path_pieces) == 5) {
      // A reference field path is of the form
      // jsonapi/ENTITY_TYPE/BUNDLE/UUID/FIELD_NAME.
      // The references list can either be single- or multi-valued.
      if (isset($data['data']['id'])) {
        $entity_data =& $data['data'];

        $old = $entity_data['id'];

        // Mock the incoming UUIDs.
        static::mockJsonApiUuids($entity_data);
        Assert::assertNotEquals($old, $entity_data['id']);
      }
      else {
        foreach ($data['data'] as &$entity_data) {
          $old = $entity_data['id'];

          // Mock the incoming UUIDs.
          static::mockJsonApiUuids($entity_data);
          Assert::assertNotEquals($old, $entity_data['id']);
        }
      }
    }

    return new GuzzleResponse(200, [], Json::encode($data));
  }

  /**
   * Helper function to replace entity UUIDs in JSONAPI data with mocked values.
   *
   * This replaces:
   * - The UUID in the entity data's 'id' field.
   * - The UUIDs in the URL in the 'related' property of relationships.
   *
   * @param &$entity_data
   *   The JSONAPI data for a single entity, passed by reference.
   */
  static protected function mockJsonApiUuids(&$entity_data) {
    $real_uuid = $entity_data['id'];

    $mocked_uuid = static::MOCKED_UUID_PREFIX . $real_uuid;
    $entity_data['id'] = $mocked_uuid;

    if (isset($entity_data['relationships'])) {
      foreach ($entity_data['relationships'] as &$relationship_data) {
        if (empty($relationship_data['data'])) {
          continue;
        }

        if (array_is_list($relationship_data['data'])) {
          // Multiple-valued reference field.
          foreach ($relationship_data['data'] as $delta => &$relationship_data_item) {
            $reference_uuid = $relationship_data_item['id'];
            $mocked_reference_uuid = static::MOCKED_UUID_PREFIX . $reference_uuid;
            $relationship_data_item['id'] = $mocked_reference_uuid;
          }
        }
        else {
          // Single-valued reference field.
          $reference_uuid = $relationship_data['data']['id'];
          $mocked_reference_uuid = static::MOCKED_UUID_PREFIX . $reference_uuid;
          $relationship_data['data']['id'] = $mocked_reference_uuid;
        }
      }
    }
  }

  /**
   * Helper function to create the import config used for the test.
   */
  protected function createImportConfig() {
    $processor_plugin_manager = $this->container->get('plugin.manager.entity_share_client_import_processor');

    $import_processor_settings = $this->getImportConfigProcessorSettings();
    foreach ($import_processor_settings as $plugin_id => &$settings) {
      if ($settings['weights'] == static::PLUGIN_DEFINITION_STAGES) {
        $definition = $processor_plugin_manager->getDefinition($plugin_id);
        $settings['weights'] = $definition['stages'];
      }
    }

    $import_config_storage = $this->entityTypeManager->getStorage('import_config');
    $import_config = $import_config_storage->create([
      'id' => 'test_import_config',
      'label' => $this->randomString(),
      'import_maxsize' => 50,
      'import_processor_settings' => $import_processor_settings,
    ]);
    $import_config->save();
    $this->importConfig = $import_config;
  }

  /**
   * Helper function to create the import config used for the test.
   *
   * @return array
   *   The import processors config. The 'weights' property in a configuration
   *   array can be set to static::PLUGIN_DEFINITION_STAGES to use the default
   *   stages and weights from a processor plugin's definition.
   */
  protected function getImportConfigProcessorSettings(): array {
    // Only locked import processors are enabled by default.
    return [
      'default_data_processor' => [
        'policy' => EntityImportStatusInterface::IMPORT_POLICY_DEFAULT,
        'update_policy' => FALSE,
        'weights' => static::PLUGIN_DEFINITION_STAGES,
      ],
      'entity_reference' => [
        'max_recursion_depth' => -1,
        'weights' => static::PLUGIN_DEFINITION_STAGES,
      ],
    ];
  }

  /**
   * Helper function to create the remote that points to the site itself.
   */
  protected function createRemote() {
    $remote_storage = $this->entityTypeManager->getStorage('remote');
    $remote = $remote_storage->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      // The remote URL is immaterial, as we intercept requests with our mocked
      // Guzzle middleware.
      'url' => 'http://remote.org/',
    ]);

    $auth_plugin = $this->container->get('plugin.manager.entity_share_client_authorization')->createInstance('anonymous');
    $remote->mergePluginConfig($auth_plugin);
    $remote->save();

    $this->remote = $remote;
  }

  /**
   * Helper function to create channels used for the test.
   *
   * @param string $entity_type_id
   *   The entity type ID to create the channel for.
   * @param string $bundle
   *   The entity bundle to create the channel for.
   * @param string $language
   *   The language to create the channel for.
   */
  protected function createChannel(string $entity_type_id, string $bundle, string $language): void {
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => $entity_type_id . '_' . $bundle . '_' . $language,
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => $entity_type_id,
      'channel_bundle' => $bundle,
      'channel_langcode' => $language,
      'access_by_permission' => TRUE,
      'authorized_roles' => [],
      'authorized_users' => [],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * Helper function to create the content required for the tests.
   */
  protected function prepareContent() {
    $entities_data = $this->getEntitiesData();
    $channel_data = [];

    foreach ($entities_data as $entity_type_id => $data_per_languages) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

      if (!isset($this->entities[$entity_type_id])) {
        $this->entities[$entity_type_id] = [];
      }

      foreach ($data_per_languages as $langcode => $entity_data) {
        foreach ($entity_data as $entity_key => $entity_data_per_field) {
          $prepared_entity_data = $this->prepareEntityData($entity_type_id, $entity_data_per_field);

          // If the entity has already been created, create a translation.
          if (isset($this->entities[$entity_type_id][$entity_key])) {
            $entity = $this->entities[$entity_type_id][$entity_key];
            $entity->addTranslation($langcode, $prepared_entity_data);
            $entity->save();
          }
          else {
            // Use the key in the entity data as the UUID. This means that
            // entities can easily be matched up with the test source data, and
            // that we can use a deterministic mapping function between source
            // entity UUIDs and pulled entity UUIDs.
            $prepared_entity_data['uuid'] = $entity_key;

            $entity = $entity_storage->create($prepared_entity_data);
            $entity->save();
          }

          $this->entities[$entity_type_id][$entity_key] = $entity;

          // Track the entities, bundles, and languages so we know what channels
          // to create.
          $channel_data[$entity_type_id][$entity->bundle()][$langcode] = TRUE;
        }
      }
    }
  }


  /**
   * Helper function to import one channel.
   *
   * @param string $channel_id
   *   The channel ID.
   */
  protected function pullChannel(string $channel_id) {
    /** @var \Drupal\entity_share_client\Service\ImportServiceInterface $import_service */
    $import_service = $this->container->get('entity_share_client.import_service');

    $import_context = new ImportContext($this->remote->id(), $channel_id, 'test_import_config');

    // Import the channel. This sets up a batch as it's meant to be called from
    // a UI.
    $import_service->importChannel($import_context);

    // Grab the batch, and call its operations ourselves.
    $batch = &batch_get();
    // Sanity check that the batch had operations set on it. If that's not the
    // case, there's a problem with the Entity Share setup, such as the channel
    // not existing or not having access.
    $this->assertNotEmpty($batch['sets']);

    $batch_context = [];
    foreach ($batch['sets'][0]['operations'] as $batch_operation) {
      [$operation_callback, $operation_parameters] = $batch_operation;

      $operation_parameters[] =& $batch_context;

      // We don't need to check the $context for whether to re-call the
      // operation, as no test should require more than one pass at this.
      // @todo: Sanity check the number of entities in the test to make sure!
      call_user_func_array($operation_callback, $operation_parameters);
    }

    // Reset the batch, otherwise it will persist.
    $batch = NULL;
    // Reset the runtime import context.
    $this->container->get('entity_share_client.import_service')->resetRuntimeImportContext();
  }

  /**
   * Asserts that all pulled entities match their source.
   *
   * The following fields are skipped in the comparison:
   * - The entity ID field.
   * - The entity UUID field.
   * - Fields given in $skipped_fields
   *
   * Reference fields which were specified in the test entity data are compared
   * using source and pulled UUIDs.
   *
   * @param array $skipped_fields
   *   A nested array of the names of fields to skip. Successive keys are
   *   entity type IDs then entity bundle names. Values are field names.
   */
  protected function assertAllPulledEntitiesMatchSource(array $skipped_fields = []) {
    // Look at all the entities in the test data.
    $entity_data = $this->getEntitiesData();
    foreach (array_keys($entity_data) as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $id_field = $entity_type->getKey('id');
      $uuid_field = $entity_type->getKey('uuid');

      foreach (array_keys($entity_data[$entity_type_id]) as $langcode) {
        foreach (array_keys($entity_data[$entity_type_id][$langcode]) as $source_uuid) {
          $mocked_uuid = static::MOCKED_UUID_PREFIX . $source_uuid;

          $source_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $source_uuid);
          $pulled_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $mocked_uuid);

          // Get the array representation of the entities.
          $source_array = $source_entity->toArray();
          $pulled_array = $pulled_entity->toArray();

          // Remove the ID and UUID field values, as those will be different.
          unset($source_array[$id_field]);
          unset($pulled_array[$id_field]);

          unset($source_array[$uuid_field]);
          unset($pulled_array[$uuid_field]);

          // Remove any skipped fields.
          if (isset($skipped_fields[$entity_type_id][$source_entity->bundle()])) {
            foreach ($skipped_fields[$entity_type_id][$source_entity->bundle()] as $skipped_field_name) {
              unset($source_array[$skipped_field_name]);
              unset($pulled_array[$skipped_field_name]);
            }
          }

          // Handle reference fields, as the values will not be the same.
          foreach ($this->referenceFieldTypes as $field_type) {
            $field_map = $this->entityFieldManager->getFieldMapByFieldType($field_type);
            foreach (array_keys($field_map[$entity_type_id]) as $reference_field_name) {
              // Skip fields not in the test entity data.
              if (!isset($entity_data[$entity_type_id][$langcode][$source_uuid][$reference_field_name])) {
                continue;
              }

              foreach ($source_entity->{$reference_field_name} as $delta => $field_item) {
                // @todo Use of the 'entity' property might need adjusting for
                // other reference field types.
                $source_referenced_entity = $field_item->entity;
                $source_referenced_entity_uuid = $source_referenced_entity->uuid();

                $pulled_referenced_entity = $pulled_entity->get($reference_field_name)[$delta]->entity;
                $pulled_referenced_entity_uuid = $pulled_referenced_entity->uuid();

                $this->assertEquals(static::MOCKED_UUID_PREFIX . $source_referenced_entity_uuid, $pulled_referenced_entity_uuid, "Reference field {$reference_field_name} does not match in source and pulled entities.");
              }

              // Remove the fields so they are not used in the exact comparison.
              unset($source_array[$reference_field_name]);
              unset($pulled_array[$reference_field_name]);
            }
          }

          // Remaining fields in the two entity arrays should be equal.
          $this->assertEquals($source_array, $pulled_array);
        }
      }
    }
  }

  /**
   * Loads a source entity based on the source UUID.
   *
   * This is just a wrapper but it helps with DX to have a method matching
   * static::loadPulledEntity().
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $source_uuid
   *   The source entity UUID, that is, the key in the array returned by
   *   static::getEntitiesData().
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if it exists. NULL otherwise.
   */
  protected function loadSourceEntity(string $entity_type_id, string $source_uuid) {
    $source_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $source_uuid);
    return $source_entity;
  }

  /**
   * Loads a pulled entity based on the source UUID.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $source_uuid
   *   The source entity UUID, that is, the key in the array returned by
   *   static::getEntitiesData().
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if it exists. NULL otherwise.
   */
  protected function loadPulledEntity(string $entity_type_id, string $source_uuid) {
    $mocked_uuid = static::MOCKED_UUID_PREFIX . $source_uuid;
    $pulled_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $mocked_uuid);
    return $pulled_entity;
  }

  /**
   * Defines the data for entities to create in the test.
   *
   * @return array
   *   An nested array whose successive keys are:
   *   - Entity type ID
   *   - Langcode
   *   - A unique string identifying the entity. This is used as the value for
   *     entity's uuid field when the entity is created.
   *   The value is an array of entity data suitable for
   *   \Drupal\Core\Entity\EntityStorageInterface::create(), with the following
   *   special cases:
   *   - The UUID field must not be included.
   *   - Reference fields must be an array with deltas, and the field value for
   *     each delta must be a numeric array with the following values:
   *     - The target entity type ID
   *     - The target entity UUID of an entity that is listed earlier in this
   *       method's return data, that is, one of the identifying string keys.
   *
   * @see static::prepareEntityData()
   */
  abstract protected function getEntitiesData(): array;

  /**
   * Prepares entity data for creating source entities.
   *
   * Field data for entity reference fields is converted to entity IDs from
   * source entities. The bundle reference field is not processed in this way.
   *
   * @param array $entity_data
   *   The array of entity data from static::getEntitiesData().
   *
   * @return array
   *   An array of entity data.
   */
  protected function prepareEntityData(string $entity_type_id, array $entity_data): array {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    foreach ($this->referenceFieldTypes as $field_type) {
      $field_map = $this->entityFieldManager->getFieldMapByFieldType($field_type);
      foreach (array_keys($field_map[$entity_type_id]) as $reference_field_name) {
        // Skip the field if there is no data for it.
        if (!isset($entity_data[$reference_field_name])) {
          continue;
        }

        // Skip the bundle field. We also skip fields that point to config
        // entities, but probably YAGNI.
        if ($reference_field_name == $entity_type->getKey('bundle')) {
          continue;
        }

        foreach ($entity_data[$reference_field_name] as $delta => $field_data) {
          [$referenced_entity_type, $referenced_entity_source_uuid] = $field_data;

          $referenced_entity = $this->entityRepository->loadEntityByUuid($referenced_entity_type, $referenced_entity_source_uuid);
          // If the entity doesn't exist, then there is a problem with the
          // test's entity data.
          $this->assertNotEmpty($referenced_entity);

          $entity_data[$reference_field_name][$delta] = $referenced_entity->id();
        }
      }
    }

    return $entity_data;
  }

}

/**
 * Replacement entity_share_client.remote_manager service.
 *
 * This forces throwing of exceptions in JSONAPI requests to the server, so that
 * errors in JSONAPI requests give useful feedback.
 *
 * @see PullKernelTestBase::fromOptions()
 */
class KernelTestRemoteManager extends RemoteManager {

  /**
   * {@inheritdoc}
   */
  protected function doRequest(ClientInterface $client, $method, $url, array $options = []) {
    // Force rethrowing of exceptions.
    $options['rethrow'] = TRUE;

    return parent::doRequest($client, $method, $url, $options);
  }

}


<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface;
use Drupal\entity_share_client\RuntimeImportContext;
use Psr\Log\LoggerInterface;

/**
 * Class ImportService.
 *
 * This class is responsible to handle import from ImportContext.
 *
 * @package Drupal\entity_share_client\Service
 */
class ImportService implements ImportServiceInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The import config manipulator service.
   *
   * @var \Drupal\entity_share_client\Service\ImportConfigManipulatorInterface
   */
  protected $importConfigManipulator;

  /**
   * The JSON:API helper service.
   *
   * @var \Drupal\entity_share_client\Service\JsonapiHelperInterface
   */
  protected $jsonapiHelper;

  /**
   * The runtime import context.
   *
   * @var \Drupal\entity_share_client\RuntimeImportContext
   */
  protected $runtimeImportContext;

  /**
   * The import processors instances by stages.
   *
   * @var \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[][]
   */
  protected $importProcessors;

  /**
   * RemoteManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Drupal\entity_share_client\Service\ImportConfigManipulatorInterface $import_config_manipulator
   *   The import config manipulator service.
   * @param \Drupal\entity_share_client\Service\JsonapiHelperInterface $jsonapi_helper
   *   The JSON:API helper service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    MessengerInterface $messenger,
    RemoteManagerInterface $remote_manager,
    ImportConfigManipulatorInterface $import_config_manipulator,
    JsonapiHelperInterface $jsonapi_helper
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->remoteManager = $remote_manager;
    $this->importConfigManipulator = $import_config_manipulator;
    $this->jsonapiHelper = $jsonapi_helper;
    $this->runtimeImportContext = new RuntimeImportContext();
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
   */
  public function importEntities(ImportContext $context, array $uuids, bool $is_batched = TRUE) {
    if (!$this->prepareImport($context)) {
      return [];
    }
    // Add the selected UUIDs to the URL.
    // We do not handle offset or limit as we provide a maximum of 50 UUIDs.
    $url = $this->runtimeImportContext->getChannelUrl();
    $prepared_url = EntityShareUtility::prepareUuidsFilteredUrl($url, $uuids);

    if ($is_batched) {
      $batch = [
        'title' => $this->t('Import entities'),
        'operations' => [
          [
            '\Drupal\entity_share_client\ImportBatchHelper::importUrlBatch',
            [$context, $prepared_url],
          ],
        ],
        'finished' => '\Drupal\entity_share_client\ImportBatchHelper::importUrlBatchFinished',
      ];

      batch_set($batch);
    }
    else {
      return $this->importFromUrl($prepared_url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importChannel(ImportContext $context) {
    if (!$this->prepareImport($context)) {
      return;
    }

    $log_variables = [
      '@remote_id' => $context->getRemoteId(),
      '@channel_id' => $context->getChannelId(),
    ];

    $channel_count = $context->getRemoteChannelCount();
    // Check how much content is in the channel if the count has not been
    // provided before.
    if ($channel_count == NULL) {
      $url_uuid = $this->runtimeImportContext->getChannelUrlUuid();
      $response = $this->jsonApiRequest('GET', $url_uuid);

      if (is_null($response)) {
        $this->logger->error('An error occurred while requesting the UUID URL for the remote website @remote_id and channel @channel_id', $log_variables);
        $this->messenger->addError($this->t('An error occurred while requesting the UUID URL for the remote website @remote_id and channel @channel_id', $log_variables));
        return;
      }

      $json = Json::decode((string) $response->getBody());

      if (isset($json['errors'])) {
        $this->logger->error('An error occurred while requesting the UUID URL for the remote website @remote_id and channel @channel_id', $log_variables);
        $this->messenger->addError($this->t('An error occurred while requesting the UUID URL for the remote website @remote_id and channel @channel_id', $log_variables));
        return;
      }
      elseif (!isset($json['meta']['count'])) {
        $this->logger->error('There is no count of the number of entities to import for the remote website @remote_id and channel @channel_id', $log_variables);
        $this->messenger->addError($this->t('There is no count of the number of entities to import for the remote website @remote_id and channel @channel_id', $log_variables));
        return;
      }

      $channel_count = (int) $json['meta']['count'];
    }

    if ($channel_count == 0) {
      $this->logger->info('Nothing to import for the remote website @remote_id and channel @channel_id', $log_variables);
      $this->messenger->addMessage($this->t('Nothing to import for the remote website @remote_id and channel @channel_id', $log_variables));
      return;
    }

    // Using the number of entities on the channel, we can generate all the
    // urls of the channel's pages, and so prepare all the operations.
    $step = $this->runtimeImportContext->getImportMaxSize();
    $url = $this->runtimeImportContext->getChannelUrl();
    $parsed_url = UrlHelper::parse($url);
    $parsed_url['query']['page']['limit'] = $step;

    // If the count is a multiple of the step, the last offset is equal to the
    // number of content and so the last offset will have no data.
    // So we remove 1.
    $operations = [];
    if ($channel_count >= $step) {
      $offsets = range(0, $channel_count - 1, $step);
      foreach ($offsets as $offset) {
        $parsed_url['query']['page']['offset'] = $offset;
        $query = UrlHelper::buildQuery($parsed_url['query']);
        $prepared_url = $parsed_url['path'] . '?' . $query;
        $operations[] = [
          '\Drupal\entity_share_client\ImportBatchHelper::importUrlBatch',
          [$context, $prepared_url],
        ];
      }
    }
    // Only one page.
    else {
      $operations[] = [
        '\Drupal\entity_share_client\ImportBatchHelper::importUrlBatch',
        [$context, $url],
      ];
    }

    $batch = [
      'title' => $this->t('Import channel'),
      'operations' => $operations,
      'progress_message' => $this->t('Imported pages: @current of @total.'),
      'finished' => '\Drupal\entity_share_client\ImportBatchHelper::importUrlBatchFinished',
    ];

    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromUrl(string $url) {
    $response = $this->jsonApiRequest('GET', $url);
    if (is_null($response)) {
      return [];
    }
    $json = Json::decode((string) $response->getBody());
    if (!isset($json['data'])) {
      return [];
    }
    $entity_list_data = EntityShareUtility::prepareData($json['data']);
    return $this->importEntityListData($entity_list_data);
  }

  /**
   * {@inheritdoc}
   */
  public function importEntityListData(array $entity_list_data) {
    $imported_entity_ids = [];

    foreach (EntityShareUtility::prepareData($entity_list_data) as $entity_data) {
      foreach ($this->importProcessors[ImportProcessorInterface::STAGE_PREPARE_ENTITY_DATA] as $import_processor) {
        $import_processor->prepareEntityData($this->runtimeImportContext, $entity_data);
      }

      foreach ($this->importProcessors[ImportProcessorInterface::STAGE_IS_ENTITY_IMPORTABLE] as $import_processor) {
        if (!$import_processor->isEntityImportable($this->runtimeImportContext, $entity_data)) {
          // Skip the import process for this entity data.
          continue 2;
        }
      }

      foreach ($this->importProcessors[ImportProcessorInterface::STAGE_PREPARE_IMPORTABLE_ENTITY_DATA] as $import_processor) {
        $import_processor->prepareImportableEntityData($this->runtimeImportContext, $entity_data);
      }

      $processed_entity = $this->getProcessedEntity($entity_data);
      $imported_entity_ids[$processed_entity->uuid()] = $processed_entity->id();

      // Prevent infinite loop.
      // Check if we try to import an already imported entity translation.
      // We can't check this in the STAGE_IS_ENTITY_IMPORTABLE stage because we
      // need to obtain the entity ID to return it for entity reference fields.
      $processed_entity_langcode = $processed_entity->language()->getId();
      $processed_entity_uuid = $processed_entity->uuid();
      if ($this->runtimeImportContext->isEntityTranslationImported($processed_entity_langcode, $processed_entity_uuid)) {
        continue;
      }
      else {
        // Store data to prevent the entity of being re-imported.
        $this->runtimeImportContext->addImportedEntity($processed_entity_langcode, $processed_entity_uuid);
      }

      foreach ($this->importProcessors[ImportProcessorInterface::STAGE_PROCESS_ENTITY] as $import_processor) {
        $import_processor->processEntity($this->runtimeImportContext, $processed_entity, $entity_data);
      }

      $processed_entity->save();

      foreach ($this->importProcessors[ImportProcessorInterface::STAGE_POST_ENTITY_SAVE] as $import_processor) {
        $import_processor->postEntitySave($this->runtimeImportContext, $processed_entity);
      }
    }
    return $imported_entity_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImport(ImportContext $context) {
    $remote_id = $context->getRemoteId();
    $channel_id = $context->getChannelId();
    $import_config_id = $context->getImportConfigId();
    $remote = NULL;
    $import_config = NULL;
    $log_variables = [];
    $log_variables['@remote_id'] = $remote_id;
    $log_variables['@channel_id'] = $channel_id;
    $log_variables['@import_config_id'] = $import_config_id;

    // Prepare import processors.
    if (is_null($import_config_id)) {
      $this->logger->error('No import config ID provided.');
      $this->messenger->addError($this->t('No import config ID provided.'));
      return FALSE;
    }
    try {
      /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config */
      $import_config = $this->entityTypeManager->getStorage('import_config')
        ->load($import_config_id);
    }
    catch (\Exception $exception) {
      $this->logger->error('Impossible to load the import config with the ID: @import_config_id', $log_variables);
      $this->messenger->addError($this->t('Impossible to load the import config with the ID: @import_config_id', $log_variables));
    }
    if (is_null($import_config)) {
      $this->logger->error('Impossible to load the import config with the ID: @import_config_id', $log_variables);
      $this->messenger->addError($this->t('Impossible to load the import config with the ID: @import_config_id', $log_variables));
      return FALSE;
    }
    $this->importProcessors = $this->importConfigManipulator->getImportProcessorsByStages($import_config);

    // Prepare runtimeImportContext.
    try {
      /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
      $remote = $this->entityTypeManager->getStorage('remote')
        ->load($remote_id);
    }
    catch (\Exception $exception) {
      $this->logger->error('Impossible to load the remote website with the ID: @remote_id', $log_variables);
      $this->messenger->addError($this->t('Impossible to load the remote website with the ID: @remote_id', $log_variables));
    }
    // Check that the remote exists.
    if (is_null($remote)) {
      return FALSE;
    }
    $this->runtimeImportContext->setRemote($remote);

    // Check that the channel exists and that we can get the channel
    // information.
    $channels_info = $this->remoteManager->getChannelsInfos($remote);
    if (!isset($channels_info[$channel_id])) {
      $this->logger->error('Impossible to obtain the channel @channel_id on the remote website with the ID: @remote_id', $log_variables);
      $this->messenger->addError($this->t('Impossible to obtain the channel @channel_id on the remote website with the ID: @remote_id', $log_variables));
      return FALSE;
    }
    $this->runtimeImportContext->setChannelId($channel_id);
    $this->runtimeImportContext->setChannelLabel($channels_info[$channel_id]['label']);
    $this->runtimeImportContext->setChannelUrl($channels_info[$channel_id]['url']);
    $this->runtimeImportContext->setChannelUrlUuid($channels_info[$channel_id]['url_uuid']);
    $this->runtimeImportContext->setChannelEntityType($channels_info[$channel_id]['channel_entity_type']);
    $this->runtimeImportContext->setChannelBundle($channels_info[$channel_id]['channel_bundle']);
    $this->runtimeImportContext->setChannelSearchConfiguration($channels_info[$channel_id]['search_configuration']);
    $this->runtimeImportContext->setImportMaxSize(EntityShareUtility::getMaxSize($import_config, $channel_id, $channels_info));

    // Get field mappings.
    $this->runtimeImportContext->setFieldMappings($this->remoteManager->getfieldMappings($remote));

    $this->runtimeImportContext->setImportService($this);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeImportContext() {
    return $this->runtimeImportContext;
  }

  /**
   * {@inheritdoc}
   */
  public function request($method, $url) {
    return $this->remoteManager->request($this->runtimeImportContext->getRemote(), $method, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonApiRequest($method, $url) {
    return $this->remoteManager->jsonApiRequest($this->runtimeImportContext->getRemote(), $method, $url);
  }

  /**
   * Helper function.
   *
   * Encapsulates the logic of detection of which content entity to manipulate.
   *
   * @param array $entity_data
   *   JSON:API data for an entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity to be processed.
   */
  protected function getProcessedEntity(array $entity_data) {
    // @todo Avoid duplicated code (and duplicate execution?) with
    // DefaultDataProcessor.
    $field_mappings = $this->runtimeImportContext->getFieldMappings();
    $parsed_type = explode('--', $entity_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_bundle = $parsed_type[1];
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_keys = $entity_storage->getEntityType()->getKeys();
    $data_uuid = $entity_data['id'];

    $langcode_public_name = FALSE;
    if (!empty($entity_keys['langcode']) && isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']])) {
      $langcode_public_name = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']];
    }
    $data_langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    if ($langcode_public_name && !empty($entity_data['attributes'][$langcode_public_name])) {
      $data_langcode = $entity_data['attributes'][$langcode_public_name];
    }

    // Check if an entity already exists.
    // JSON:API no longer includes uuid in attributes so we're using id
    // instead. See https://www.drupal.org/node/2984247.
    $existing_entities = $entity_storage
      ->loadByProperties(['uuid' => $data_uuid]);

    // Here is the supposition that we are importing a list of content
    // entities. Currently this is ensured by the fact that it is not possible
    // to make a channel on config entities and on users. And that in the
    // relationshipHandleable() method we prevent handling config entities and
    // users relationships.
    // We can't create a placeholder entity manually and then populating using
    // the JSON values directly because otherwise we would lose all the
    // denormalization processes. Especially those created for JSON:API
    // Extras.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $remote_entity = $this->jsonapiHelper->extractEntity($entity_data);

    // New entity.
    if (empty($existing_entities)) {
      // Save the entity to have an ID for entity reference management.
      // If A -> B -> A, and A is not saved, when recursively importing A, B
      // will not be able to reference A.
      $remote_entity->save();
      $processed_entity = $remote_entity;
    }
    // Existing entity.
    else {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $existing_entity */
      $existing_entity = array_shift($existing_entities);

      if ($existing_entity->language()->isLocked()) {
        // The existing entity was in an untranslatable language like "und",
        // so we convert it to the new language.
        $existing_entity->set('langcode', $data_langcode);
      }

      $has_translation = $existing_entity->hasTranslation($data_langcode);
      // Update the existing translation.
      if ($has_translation) {
        $existing_translation = $existing_entity->getTranslation($data_langcode);

        // Need to set those field values now with the denormalized remote
        // entity, so that we have data processed by denormalization processes.
        // For example, JSON:API extras field enhancers plugins.
        foreach (array_keys($entity_data['attributes']) as $field_public_name) {
          $field_internal_name = array_search($field_public_name, $field_mappings[$entity_type_id][$entity_bundle]);
          if ($field_internal_name && $existing_translation->hasField($field_internal_name)) {
            $existing_translation->set(
              $field_internal_name,
              $remote_entity->get($field_internal_name)->getValue()
            );
          }
          else {
            $this->logger->notice('Error during import. The field @field does not exist.', ['@field' => $field_internal_name]);
          }
        }
        $processed_entity = $existing_translation;
      }
      // Create the new translation.
      else {
        $remote_entity_as_array = $remote_entity->toArray();
        $existing_entity->addTranslation($data_langcode, $remote_entity_as_array);
        $processed_entity = $existing_entity->getTranslation($data_langcode);
      }
    }
    return $processed_entity;
  }

}

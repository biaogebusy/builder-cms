<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Service to handle presentation of import state.
 *
 * @package Drupal\entity_share_client\Service
 */
class StateInformation implements StateInformationInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The Drupal datetime service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The import policies manager.
   *
   * @var \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager
   */
  protected $policiesManager;

  /**
   * The entity import status. NULL if not found.
   *
   * @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface|null
   */
  protected $entityImportStatus;

  /**
   * StateInformation constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Drupal time service.
   * @param \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager $policies_manager
   *   The import policies manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ResourceTypeRepositoryInterface $resource_type_repository,
    TimeInterface $time,
    ImportPolicyPluginManager $policies_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->time = $time;
    $this->policiesManager = $policies_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusInfo(array $data) {
    // Reset the entity import status.
    $this->entityImportStatus = NULL;
    $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_UNDEFINED);

    // Get the entity type and entity storage.
    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    try {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    }
    catch (\Exception $exception) {
      $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_UNKNOWN);
      return $status_info;
    }

    // Check if an entity already exists.
    $existing_entities = $entity_storage
      ->loadByProperties(['uuid' => $data['id']]);

    if (empty($existing_entities)) {
      $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_NEW);
    }
    // An entity already exists.
    // Check if the entity type has a changed date.
    else {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $existing_entity */
      $existing_entity = array_shift($existing_entities);

      $resource_type = $this->resourceTypeRepository->get(
        $parsed_type[0],
        $parsed_type[1]
      );

      $changed_public_name = FALSE;
      if ($resource_type->hasField('changed')) {
        $changed_public_name = $resource_type->getPublicName('changed');
      }

      if (!empty($data['attributes'][$changed_public_name]) && method_exists($existing_entity, 'getChangedTime')) {
        $entity_changed_time = EntityShareUtility::convertChangedTime($data['attributes'][$changed_public_name]);

        $entity_keys = $entity_storage
          ->getEntityType()
          ->getKeys();
        // Case of translatable entity.
        if (isset($entity_keys['langcode']) && !empty($entity_keys['langcode'])) {
          $entity_language_id = $data['attributes'][$resource_type->getPublicName($entity_keys['langcode'])];

          // Entity has the translation.
          if ($existing_entity->hasTranslation($entity_language_id)) {
            $existing_translation = $existing_entity->getTranslation($entity_language_id);

            // Existing entity.
            if ($this->entityHasChanged($existing_translation, $entity_changed_time)) {
              $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_CHANGED, $existing_translation);
            }
            else {
              $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_SYNCHRONIZED, $existing_translation);
            }
          }
          else {
            $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_NEW_TRANSLATION, $existing_entity);
          }
        }
        // Case of untranslatable entity.
        else {
          // Existing entity.
          if ($this->entityHasChanged($existing_entity, $entity_changed_time)) {
            $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_CHANGED, $existing_entity);
          }
          else {
            $status_info = $this->statusInfoArray(StateInformationInterface::INFO_ID_SYNCHRONIZED, $existing_entity);
          }
        }
      }
    }

    return $status_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusDefinition(string $status_info_id) {
    $definitions = [
      StateInformationInterface::INFO_ID_UNDEFINED => [
        'label' => $this->t('Undefined'),
        'class' => 'undefined',
      ],
      StateInformationInterface::INFO_ID_UNKNOWN => [
        'label' => $this->t('Unknown entity type'),
        'class' => 'undefined',
      ],
      StateInformationInterface::INFO_ID_NEW => [
        'label' => $this->t('New entity'),
        'class' => 'new',
      ],
      StateInformationInterface::INFO_ID_NEW_TRANSLATION => [
        'label' => $this->t('New translation'),
        'class' => 'new',
      ],
      StateInformationInterface::INFO_ID_CHANGED => [
        'label' => $this->t('Entities not synchronized'),
        'class' => 'changed',
      ],
      StateInformationInterface::INFO_ID_SYNCHRONIZED => [
        'label' => $this->t('Entities synchronized'),
        'class' => 'up-to-date',
      ],
    ];
    return $definitions[$status_info_id] ?? $definitions[StateInformationInterface::INFO_ID_UNDEFINED];
  }

  /**
   * Helper function: generates status information for a known status ID.
   *
   * @param string $status_info_id
   *   An identifier of the status info (the value of 'INFO_ID_...' constant).
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A Drupal content entity.
   *
   * @return array
   *   The same as return value of getStatusInfo().
   */
  protected function statusInfoArray(string $status_info_id, ContentEntityInterface $entity = NULL) {
    $status_definition = $this->getStatusDefinition($status_info_id);
    $status_info = [
      'label' => $status_definition['label'],
      'class' => 'entity-share-' . $status_definition['class'],
      'info_id' => $status_info_id,
      'local_entity_link' => NULL,
      'local_revision_id' => NULL,
      'policy' => '',
    ];

    if ($this->entityImportStatus) {
      $policy = $this->entityImportStatus->getPolicy();
      $policy_plugin = $this->policiesManager->getDefinition($policy, FALSE);
      $status_info['policy'] = !is_null($policy_plugin) ? $policy_plugin['label'] : $this->t('Unknown policy: @policy', [
        '@policy' => $policy,
      ]);
    }

    if ($entity instanceof ContentEntityInterface) {
      try {
        $status_info['local_entity_link'] = $entity->toUrl();
      }
      catch (UndefinedLinkTemplateException $exception) {
        // Do nothing, the link remains NULL.
      }
      // If entity type is not revisionable, this will remain NULL.
      $status_info['local_revision_id'] = $entity->getRevisionId();
    }
    return $status_info;
  }

  /**
   * Checks if the entity has changed on Remote before import.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   * @param int $remote_changed_time
   *   The timestamp of "changed" date on Remote.
   *
   * @return bool
   *   Whether the entity has changed on Remote before import.
   */
  protected function entityHasChanged(ContentEntityInterface $entity, int $remote_changed_time) {
    // We are determining if the entity has changed by comparing the dates.
    // The last import date must be after the remote changed date, otherwise
    // the entity has changed.
    $this->entityImportStatus = $this->getImportStatusOfEntity($entity);

    if ($this->entityImportStatus) {
      return $this->entityImportStatus->getLastImport() < $remote_changed_time;
    }
    // If for some reason the "Entity import status" entity doesn't exist,
    // simply compare by modification dates on remote and local.
    else {
      return $entity->getChangedTime() != $remote_changed_time;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createImportStatusOfEntity(ContentEntityInterface $entity, array $parameters) {
    try {
      $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
      $entity_import_status_data = [
        'entity_id' => $entity->id(),
        'entity_uuid' => $entity->uuid(),
        'entity_type_id' => $entity->getEntityTypeId(),
        'entity_bundle' => $entity->bundle(),
        'last_import' => $this->time->getRequestTime(),
      ];
      if ($entity_storage->getEntityType()->hasKey('langcode')) {
        $entity_import_status_data['langcode'] = $entity->language()->getId();
      }
      foreach (['remote_website', 'channel_id', 'policy'] as $additional_parameter) {
        if (!empty($parameters[$additional_parameter])) {
          $entity_import_status_data[$additional_parameter] = $parameters[$additional_parameter];
        }
      }
      $import_status_entity = $entity_storage->create($entity_import_status_data);
      $import_status_entity->save();
      return $import_status_entity;
    }
    catch (\Exception $e) {
      // @todo log the error.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getImportStatusByParameters(string $uuid, string $entity_type_id, string $langcode = NULL) {
    // A content entity can be uniquely identified by entity type, UUID and
    // language code (if entity type supports languages).
    $search_criteria = [
      'entity_uuid' => $uuid,
      'entity_type_id' => $entity_type_id,
    ];
    if ($langcode) {
      $search_criteria['langcode'] = $langcode;
    }
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface[] $import_status_entities */
    $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
    $import_status_entities = $entity_storage->loadByProperties($search_criteria);
    if (!empty($import_status_entities)) {
      return current($import_status_entities);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportStatusOfEntity(ContentEntityInterface $entity) {
    $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
    $langcode = NULL;
    if ($entity_storage->getEntityType()->hasKey('langcode')) {
      $langcode = $entity->language()->getId();
    }
    return $this->getImportStatusByParameters($entity->uuid(), $entity->getEntityTypeId(), $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteImportStatusOfEntity(EntityInterface $entity, string $langcode = NULL) {
    // If entity is not supported by "entity import", do nothing.
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if (in_array($entity->getEntityTypeId(), ['user', 'entity_import_status'])) {
      return;
    }
    if (!$entity->uuid()) {
      return;
    }
    $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
    $search_criteria = [
      'entity_id' => $entity->id(),
      'entity_type_id' => $entity->getEntityTypeId(),
    ];
    if ($entity_storage->getEntityType()->hasKey('uuid')) {
      $search_criteria['entity_uuid'] = $entity->uuid();
    }
    if ($langcode && $entity_storage->getEntityType()->hasKey('langcode')) {
      $search_criteria['langcode'] = $langcode;
    }
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface[] $import_status_entities */
    $import_status_entities = $entity_storage->loadByProperties($search_criteria);
    if ($import_status_entities) {
      foreach ($import_status_entities as $import_status_entity) {
        $import_status_entity->delete();
      }
    }
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_client\Service\EntityReferenceHelper;
use Drupal\entity_share_client\Service\JsonapiHelperInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginManager;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Entity parser.
 *
 * @package Drupal\entity_share_diff\Service
 */
class EntityParser implements EntityParserInterface {

  /**
   * The diff field builder plugin manager.
   *
   * @var \Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginManager
   */
  protected $diffGeneratorManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The jsonapi helper.
   *
   * @var \Drupal\entity_share_client\Service\JsonapiHelperInterface
   */
  protected $jsonapiHelper;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Entity reference helper service.
   *
   * @var \Drupal\entity_share_client\Service\EntityReferenceHelper
   */
  protected $entityReferenceHelper;

  /**
   * The ES Remote config entity, or FALSE if parsing a local entity.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface|null
   */
  protected $remote;

  /**
   * Temporary array of processed entities, needed to avoid infinite loop.
   *
   * @var array
   */
  private $processedEntities;

  /**
   * Class constructor.
   *
   * @param \Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginManager $diff_generator_manager
   *   The Diff manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The ES remote manager.
   * @param \Drupal\entity_share_client\Service\JsonapiHelperInterface $jsonapi_helper
   *   The ES JSON:API helper.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\entity_share_client\Service\EntityReferenceHelper $entity_reference_helper
   *   The entity reference helper service.
   */
  public function __construct(
    DiffGeneratorPluginManager $diff_generator_manager,
    LanguageManagerInterface $language_manager,
    RemoteManagerInterface $remote_manager,
    JsonapiHelperInterface $jsonapi_helper,
    ResourceTypeRepositoryInterface $resource_type_repository,
    EntityReferenceHelper $entity_reference_helper
  ) {
    $this->diffGeneratorManager = $diff_generator_manager;
    $this->languageManager = $language_manager;
    $this->remoteManager = $remote_manager;
    $this->jsonapiHelper = $jsonapi_helper;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityReferenceHelper = $entity_reference_helper;
    $this->processedEntities = [
      'local' => [],
      'remote' => [],
    ];
  }

  /**
   * Returns Remote entity.
   *
   * @return \Drupal\entity_share_client\Entity\RemoteInterface|bool
   *   The ES Remote entity or FALSE.
   */
  public function getRemote() {
    return $this->remote;
  }

  /**
   * Sets Remote entity.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface|bool $remote
   *   The ES Remote entity or FALSE.
   */
  public function setRemote($remote) {
    $this->remote = $remote;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareLocalEntity(ContentEntityInterface $entity) {
    $this->setRemote(FALSE);
    return $this->parseEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRemoteEntity(array $remote_data, RemoteInterface $remote) {
    $this->setRemote($remote);
    $remote_entity = $this->jsonapiHelper->extractEntity($remote_data);
    return $this->parseEntity($remote_entity, $remote_data);
  }

  /**
   * {@inheritdoc}
   */
  public function validateNeedToProcess(string $uuid, bool $remote) {
    $main_key = $remote ? 'remote' : 'local';
    if (!in_array($uuid, $this->processedEntities[$main_key])) {
      $this->processedEntities[$main_key][] = $uuid;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper: returns JSON:API public field name of one entity's remote data.
   *
   * @param string $field_name
   *   The local Drupal field name.
   * @param array $entity_json_data
   *   The JSON:API data for a single entity.
   *
   * @return string
   *   The public field name or an empty string if not applicable.
   */
  public function getPublicFieldName(string $field_name, array $entity_json_data) {
    if (empty($entity_json_data['type'])) {
      return '';
    }
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $bundle = $parsed_type[1];

    $resource_type = $this->resourceTypeRepository->get($entity_type_id, $bundle);
    if (!$resource_type instanceof ResourceType) {
      return '';
    }
    if (!$resource_type->hasField($field_name)) {
      return '';
    }
    return $resource_type->getPublicName($field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteChangedTime(array $remote_data) {
    $changed_public_name = $this->getPublicFieldName('changed', $remote_data);
    $entity_changed_time = 0;
    if ($changed_public_name && !empty($remote_data['attributes'][$changed_public_name])) {
      $entity_changed_time = EntityShareUtility::convertChangedTime($remote_data['attributes'][$changed_public_name]);
    }
    return $entity_changed_time;
  }

  /**
   * Parses an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal entity (local or remote).
   * @param array $remote_data
   *   Used for remote entity: entity data coming from JSON:API.
   *
   * @return array
   *   Parsed data of a field, suitable for YAML parsing.
   *   Associative array, keyed by labels.
   *   Values are strings, numbers or arrays.
   */
  protected function parseEntity(ContentEntityInterface $entity, array $remote_data = NULL) {
    $result = [];
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    // Load entity of current language, otherwise fields are always compared by
    // their default language.
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }
    $irrelevant_fields = $this->getFieldsIrrelevantForDiff($entity);
    // Loop through entity fields and transform every FieldItemList object
    // into an array of strings according to field type specific settings.
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
    foreach ($entity as $item_key => $field_items) {
      // Prepare remote information for reference fields, if exists.
      if ($this->getRemote()) {
        $public_key = $this->getPublicFieldName($item_key, $remote_data);
      }
      else {
        $public_key = $item_key;
      }
      $remote_field_data = [];
      // Determine if a field should be parsed or skipped.
      // If a field is an entity reference, then eliminate it in
      // case the relationship is not handleable.
      switch ($this->entityReferenceHelper->relationshipHandleable($field_items)) {
        case EntityReferenceHelper::RELATIONSHIP_HANDLEABLE:
          $should_parse = TRUE;
          if (isset($remote_data['relationships'][$public_key])) {
            $remote_field_data = $remote_data['relationships'][$public_key];
            if (isset($remote_field_data['data'])) {
              $remote_field_data['data'] = EntityShareUtility::prepareData($remote_field_data['data']);
            }
          }
          break;

        case EntityReferenceHelper::RELATIONSHIP_NOT_HANDLEABLE:
          $should_parse = FALSE;
          break;

        case EntityReferenceHelper::RELATIONSHIP_NOT_ENTITY_REFERENCE:
          $should_parse = !in_array($item_key, $irrelevant_fields);
          break;
      }
      if (!$should_parse) {
        continue;
      }
      $parsed_field = $this->parseField($item_key, $field_items, $remote_field_data);
      if ($parsed_field != NULL) {
        $field_label = (string) $field_items->getFieldDefinition()->getLabel();
        $result[$field_label] = $parsed_field;
      }
    }
    return $result;
  }

  /**
   * Parses a field or property of entity.
   *
   * @param string $item_key
   *   The field key (machine name).
   * @param \Drupal\Core\Field\FieldItemListInterface $field_items
   *   Field items.
   * @param array $remote_field_data
   *   Used for remote entity: field data coming from JSON:API.
   *
   * @return array
   *   Parsed data of a field, suitable for YAML parsing.
   */
  protected function parseField(string $item_key, FieldItemListInterface $field_items, array $remote_field_data = []) {
    $build = [];
    $field_type = $field_items->getFieldDefinition()->getType();
    $plugin = $this->diffGeneratorManager->createInstanceForFieldDefinition($field_type);
    if ($plugin) {
      // Pass the Remote config entity to the plugin.
      $remote = $this->getRemote();
      if ($remote) {
        $plugin->setRemote($remote);
      }
      // Let the plugin build the value.
      $build = $plugin->build($field_items, $remote_field_data);
      if (!empty($build)) {
        // In case of a single field, flatten the array.
        $cardinality = $field_items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
        if ($cardinality == 1 && is_array($build)) {
          $build = current($build);
        }
      }
    }
    return $build;
  }

  /**
   * Checks if the entity should be embedded into Diff or just listed with UUID.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return bool
   *   Whether entity of this type is embeddable or not.
   */
  public function referenceEmbeddable(string $entity_type_id) {
    $embeddable_types = [
      'paragraph',
      'media',
    ];
    return in_array($entity_type_id, $embeddable_types);
  }

  /**
   * Helper: lists entity properties/fields which should not appear in Diff.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal entity (local or remote).
   *
   * @return string[]
   *   Array of entity properties/fields.
   */
  protected function getFieldsIrrelevantForDiff(ContentEntityInterface $entity) {
    // Entity keys.
    $entity_keys = $entity->getEntityType()->getKeys();
    // Label and language code should be displayed in the Diff.
    unset($entity_keys['label']);
    unset($entity_keys['langcode']);
    $field_names = array_values($entity_keys);
    // Revision keys.
    $revision_keys = array_keys($entity->getEntityType()->getRevisionMetadataKeys());
    $field_names = array_merge($field_names, $revision_keys);
    // Other keys.
    $other_keys = [
      'changed',
      'created',
      // Related to translation.
      'content_translation_source',
      'content_translation_affected',
      'content_translation_outdated',
      'revision_translation_affected',
      // Related to paragraphs.
      'parent_id',
      'parent_type',
      'parent_field_name',
      // For some reason getRevisionMetadataKeys() doesn't always return these.
      'revision_timestamp',
      'revision_log',
    ];
    $field_names = array_merge($field_names, $other_keys);
    return $field_names;
  }

}

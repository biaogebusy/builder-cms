<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;

/**
 * Class RuntimeImportContext.
 *
 * Contains properties to store data during import.
 *
 * @package Drupal\entity_share_client
 */
class RuntimeImportContext {

  /**
   * The remote.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * The channel's identifier.
   *
   * @var string
   */
  protected $channelId;

  /**
   * The channel's Label.
   *
   * @var string
   */
  protected $channelLabel;

  /**
   * The channel's URL.
   *
   * @var string
   */
  protected $channelUrl;

  /**
   * The channel's URL UUID.
   *
   * @var string
   */
  protected $channelUrlUuid;

  /**
   * The channel's entity type.
   *
   * @var string
   */
  protected $channelEntityType;

  /**
   * The channel's bundle.
   *
   * @var string
   */
  protected $channelBundle;

  /**
   * The channel's search configuration.
   *
   * @var array
   */
  protected $channelSearchConfiguration;

  /**
   * The remote field mappings.
   *
   * @var array
   */
  protected $fieldMappings;

  /**
   * The import service used for the import.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * The import max size.
   *
   * @var int
   */
  protected $importMaxSize;

  /**
   * The list of the currently imported entities.
   *
   * @var array
   */
  protected $importedEntities = [];

  /**
   * The list of the entities mark for import.
   *
   * This is a different list than the $importedEntities because of some
   * processors which have to import entities before it is normally marked as
   * imported in the import service.
   *
   * So to avoid infinite loop, a second list of entities being imported is
   * created.
   *
   * @var array
   */
  protected $entitiesMarkedForImport = [];

  /**
   * The list of books that got processed.
   *
   * @var array
   */
  protected $books = [];

  /**
   * Getter.
   *
   * @return \Drupal\entity_share_client\Entity\RemoteInterface
   *   The remote.
   */
  public function getRemote(): RemoteInterface {
    return $this->remote;
  }

  /**
   * Setter.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote.
   */
  public function setRemote(RemoteInterface $remote): void {
    $this->remote = $remote;
  }

  /**
   * Getter.
   *
   * @return string
   *   The channel's identifier.
   */
  public function getChannelId(): string {
    return $this->channelId;
  }

  /**
   * Setter.
   *
   * @param string $channel_id
   *   The channel's identifier.
   */
  public function setChannelId(string $channel_id): void {
    $this->channelId = $channel_id;
  }

  /**
   * Getter.
   *
   * @return string
   *   The channel's Label.
   */
  public function getChannelLabel(): string {
    return $this->channelLabel;
  }

  /**
   * Setter.
   *
   * @param string $channelLabel
   *   The channel's Label.
   */
  public function setChannelLabel(string $channelLabel): void {
    $this->channelLabel = $channelLabel;
  }

  /**
   * Getter.
   *
   * @return string
   *   The channel's URL.
   */
  public function getChannelUrl(): string {
    return $this->channelUrl;
  }

  /**
   * Setter.
   *
   * @param string $channelUrl
   *   The channel's URL.
   */
  public function setChannelUrl(string $channelUrl): void {
    $this->channelUrl = $channelUrl;
  }

  /**
   * Getter.
   *
   * @return string
   *   The channel's URL UUID.
   */
  public function getChannelUrlUuid(): string {
    return $this->channelUrlUuid;
  }

  /**
   * Setter.
   *
   * @param string $channelUrlUuid
   *   The channel's URL UUID.
   */
  public function setChannelUrlUuid(string $channelUrlUuid): void {
    $this->channelUrlUuid = $channelUrlUuid;
  }

  /**
   * Getter.
   *
   * @return string
   *   The channel's entity type.
   */
  public function getChannelEntityType(): string {
    return $this->channelEntityType;
  }

  /**
   * Setter.
   *
   * @param string $channelEntityType
   *   The channel's entity type.
   */
  public function setChannelEntityType(string $channelEntityType): void {
    $this->channelEntityType = $channelEntityType;
  }

  /**
   * Getter.
   *
   * @return string
   *   The channel's bundle.
   */
  public function getChannelBundle(): string {
    return $this->channelBundle;
  }

  /**
   * Setter.
   *
   * @param string $channelBundle
   *   The channel's bundle.
   */
  public function setChannelBundle(string $channelBundle): void {
    $this->channelBundle = $channelBundle;
  }

  /**
   * Getter.
   *
   * @return array
   *   The channel's search configuration.
   */
  public function getChannelSearchConfiguration(): array {
    return $this->channelSearchConfiguration;
  }

  /**
   * Setter.
   *
   * @param array $channelSearchConfiguration
   *   The channel's search configuration.
   */
  public function setChannelSearchConfiguration(array $channelSearchConfiguration): void {
    $this->channelSearchConfiguration = $channelSearchConfiguration;
  }

  /**
   * Getter.
   *
   * @return array
   *   The remote field mappings.
   */
  public function getFieldMappings(): array {
    return $this->fieldMappings;
  }

  /**
   * Setter.
   *
   * @param array $fieldMappings
   *   The remote field mappings.
   */
  public function setFieldMappings(array $fieldMappings): void {
    $this->fieldMappings = $fieldMappings;
  }

  /**
   * Getter.
   *
   * @return \Drupal\entity_share_client\Service\ImportServiceInterface
   *   The import service used for the import.
   */
  public function getImportService(): ImportServiceInterface {
    return $this->importService;
  }

  /**
   * Setter.
   *
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $importService
   *   The import service used for the import.
   */
  public function setImportService(ImportServiceInterface $importService): void {
    $this->importService = $importService;
  }

  /**
   * Getter.
   *
   * @return int
   *   The import max size.
   */
  public function getImportMaxSize(): int {
    return $this->importMaxSize;
  }

  /**
   * Setter.
   *
   * @param int $importMaxSize
   *   The import max size.
   */
  public function setImportMaxSize(int $importMaxSize): void {
    $this->importMaxSize = $importMaxSize;
  }

  /**
   * Getter.
   *
   * @return array
   *   The imported entities.
   */
  public function getImportedEntities(): array {
    return $this->importedEntities;
  }

  /**
   * Clear imported entities.
   *
   * @param string $langcode
   *   The language code to reset.
   * @param string $entity_uuid
   *   The entity UUID to reset.
   */
  public function clearImportedEntities($langcode = '', $entity_uuid = '') {
    if (empty($langcode) && empty($entity_uuid)) {
      $this->importedEntities = [];
    }
    elseif (!empty($langcode) && empty(!$entity_uuid) && isset($this->importedEntities[$langcode][$entity_uuid])) {
      unset($this->importedEntities[$langcode][$entity_uuid]);
    }
    elseif (!empty($langcode) && isset($this->importedEntities[$langcode])) {
      $this->importedEntities[$langcode] = [];
    }
    elseif (!empty($entity_uuid)) {
      foreach (array_keys($this->importedEntities) as $imported_entities_langcode) {
        if (isset($this->importedEntities[$imported_entities_langcode][$entity_uuid])) {
          unset($this->importedEntities[$imported_entities_langcode][$entity_uuid]);
        }
      }
    }
  }

  /**
   * Register that an entity translation has been imported.
   *
   * @param string $langcode
   *   The language code of the translation.
   * @param string $entity_uuid
   *   The entity UUID.
   */
  public function addImportedEntity($langcode, $entity_uuid) {
    $this->importedEntities[$langcode][$entity_uuid] = $entity_uuid;
  }

  /**
   * Check if an entity translation has been imported.
   *
   * @param string $langcode
   *   The language code of the translation.
   * @param string $entity_uuid
   *   The entity UUID.
   *
   * @return bool
   *   TRUE if the translation had been imported. FALSE otherwise.
   */
  public function isEntityTranslationImported($langcode, $entity_uuid) {
    if (isset($this->importedEntities[$langcode][$entity_uuid])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Getter.
   *
   * @return array
   *   The entities marked for import.
   */
  public function getEntitiesMarkedForImport(): array {
    return $this->entitiesMarkedForImport;
  }

  /**
   * Register that an entity has been marked for import.
   *
   * @param string $entity_uuid
   *   The entity UUID.
   */
  public function addEntityMarkedForImport($entity_uuid): void {
    $this->entitiesMarkedForImport[$entity_uuid] = $entity_uuid;
  }

  /**
   * Check if an entity has been marked for import in any language.
   *
   * @param string $entity_uuid
   *   The entity UUID.
   *
   * @return bool
   *   TRUE if the entity had been marked for import. FALSE otherwise.
   */
  public function isEntityMarkedForImport(string $entity_uuid): bool {
    if (isset($this->entitiesMarkedForImport[$entity_uuid])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Getter.
   *
   * @param string $uuid
   *   The UUID of the imported book content.
   *
   * @return array
   *   The book structure if existing.
   */
  public function getBook(string $uuid): array {
    return $this->books[$uuid] ?? [];
  }

  /**
   * Setter.
   *
   * @param string $uuid
   *   The UUID of the imported book content.
   * @param array $book
   *   The book structure as provided by JSON:API Book module.
   */
  public function setBook(string $uuid, array $book): void {
    $this->books[$uuid] = $book;
  }

}

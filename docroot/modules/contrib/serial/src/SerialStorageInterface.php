<?php

namespace Drupal\serial;

// Use Drupal\Core\Entity\ContentEntityStorageInterface;.
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines an interface for serial storage classes.
 */
interface SerialStorageInterface {

  const SERIAL_FIELD_TYPE = 'serial';

  /**
   * Creates the assistant storage name for a specific field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity.
   *
   * @return string
   *   Storage name.
   */
  public function createStorageNameFromField(FieldDefinitionInterface $fieldDefinition, FieldableEntityInterface $entity);

  /**
   * Creates the storage name.
   *
   * @param string $entityTypeId
   *   Entity type id.
   * @param string $entityBundle
   *   Entity bundle (entity type) name.
   * @param string $fieldName
   *   Field name.
   *
   * @return string
   *   Storage name.
   */
  public function createStorageName($entityTypeId, $entityBundle, $fieldName);

  /**
   * Generates a unique serial value (unique per entity bundle).
   *
   * @param string $storageName
   *   Storage name.
   * @param bool $delete
   *   Indicates if temporary records should be deleted.
   *
   * @return int
   *   Unique serial id.
   */
  public function generateValueFromName($storageName, $delete = TRUE);

  /**
   * Generates a unique serial value (unique per entity bundle).
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity.
   * @param bool $delete
   *   Indicates if temporary records should be deleted.
   *
   * @return int
   *   Unique serial id.
   */
  public function generateValue(FieldDefinitionInterface $fieldDefinition, FieldableEntityInterface $entity, $delete = TRUE);

  /**
   * Gets the schema of the assistant storage for generating serial values.
   *
   * @return array
   *   Assistant storage schema.
   */
  public function getSchema();

  /**
   * Gets a lightweight map of fields across bundles filtered by field type.
   *
   * @return array
   *   An array keyed by entity type. Each value is an array which keys are
   *   field names and value is an array with two entries:
   *   - type: The field type.
   *   - bundles: An associative array of the bundles in which the field
   *     appears, where the keys and values are both the bundle's machine name.
   */
  public function getAllFields();

  /**
   * Creates an assistant serial storage for a new created field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity.
   */
  public function createStorage(FieldDefinitionInterface $fieldDefinition, FieldableEntityInterface $entity);

  /**
   * Creates an assistant serial storage for a new created field.
   *
   * @param string $storageName
   *   Storage name.
   */
  public function createStorageFromName($storageName);

  /**
   * Drops an assistant serial storage for a deleted field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity.
   */
  public function dropStorage(FieldDefinitionInterface $fieldDefinition, FieldableEntityInterface $entity);

  /**
   * Drops an assistant serial storage for a deleted field.
   *
   * @param string $storageName
   *   Storage name.
   */
  public function dropStorageFromName($storageName);

  /**
   * Initializes the value of a new serial field in existing entities.
   *
   * @param string $entityTypeId
   *   Entity type id.
   * @param string $entityBundle
   *   Entity bundle (entity type) name.
   * @param string $fieldName
   *   Field name.
   * @param int $startValue
   *   The value to start the serial increment with.
   *
   * @return int
   *   Amount of entries that were updated.
   */
  public function initOldEntries($entityTypeId, $entityBundle, $fieldName, $startValue);

}

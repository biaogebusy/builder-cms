<?php

namespace Drupal\eck\Plugin\migrate\source\d7;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 ECK Entity source from database.
 *
 * A base class that gets ECK data from the source database.
 *
 * Available configuration keys:
 * - entity_type: (optional) The machine name of the ECK entity type.
 * - bundle: (optional) The bundle name - can be a string or an array. If not
 * declared then all bundles will be retrieved.
 *
 * For additional configuration keys, refer to the parent classes:
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_eck_entity",
 *   source_module = "eck"
 * )
 */
class EckEntity extends FieldableEntity {

  /**
   * The Entity Type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The ECK Table name.
   *
   * @var string
   */
  protected $eckTable;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_manager) {
    $this->entityType = $configuration['entity_type'] ?? '';
    if (!\is_string($this->entityType)) {
      throw new MigrateException('The entity_type must be a string');
    }
    if (isset($configuration['bundle'])) {
      $this->bundle = $configuration['bundle'];
      if (!\is_string($this->bundle) && !\is_array($this->bundle)) {
        throw new MigrateException('The bundle must be a string or an array');
      }
    }
    $this->eckTable = 'eck_' . $this->entityType;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select($this->eckTable, 'eck')
      ->fields('eck');

    if (isset($this->bundle)) {
      $query->condition('eck.type', (array) $this->bundle, 'IN');
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $language = $row->getSourceProperty('language');
    $id = $row->getSourceProperty('id');
    $vid = $row->hasSourceProperty('revision_id') ? $row->getSourceProperty('revision_id') : $id;

    // Get Field API field values.
    foreach ($this->getFields($this->entityType, $this->bundle) as $field_name => $field) {
      // Ensure we're using the right language if the entity is translatable.
      $field_language = $field['translatable'] ? $language : NULL;
      $row->setSourceProperty($field_name, $this->getFieldValues($this->entityType, $field_name, $id, $vid, $field_language));
    }
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('The primary identifier for the entity'),
      'type' => $this->t('The bundle of the entity'),
      'title' => $this->t('Title'),
      'uid' => $this->t('Author'),
      'created' => $this->t('Created'),
      'changed' => $this->t('Changed'),
      'language' => $this->t('Entity language code'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'eck',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements() {
    $table_name = 'eck_' . $this->entityType;
    if (!$this->getDatabase()->schema()->tableExists($table_name)) {
      throw new RequirementsException("ECK table for '$this->entityType' does not exist");
    }
    parent::checkRequirements();
  }

}

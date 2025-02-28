<?php

namespace Drupal\replication\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * @FieldType(
 *   id = "replication_history",
 *   label = @Translation("Replication history"),
 *   description = @Translation("History information for a replication."),
 *   list_class = "\Drupal\replication\Plugin\Field\FieldType\ReplicationHistoryItemList",
 *   no_ui = TRUE
 * )
 */
class ReplicationHistoryItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'session_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['doc_write_failures'] = DataDefinition::create('integer')
      ->setLabel(t('Write failures'))
      ->setDescription(t('Number of failed document writes'))
      ->setRequired(FALSE);

    $properties['docs_read'] = DataDefinition::create('integer')
      ->setLabel(t('Documents read'))
      ->setDescription(t('Number of documents read.'))
      ->setRequired(FALSE);

    $properties['docs_written'] = DataDefinition::create('integer')
      ->setLabel(t('Documents written'))
      ->setDescription(t('Number of documents written.'))
      ->setRequired(FALSE);

    $properties['start_last_seq'] = DataDefinition::create('string')
      ->setLabel(t('Start sequence'))
      ->setDescription(t('Sequence ID where the replication started.'))
      ->setRequired(FALSE);

    $properties['end_last_seq'] = DataDefinition::create('string')
      ->setLabel(t('End sequence'))
      ->setDescription(t('Sequence ID where the replication ended.'))
      ->setRequired(FALSE);

    $properties['missing_checked'] = DataDefinition::create('integer')
      ->setLabel(t('Missing checked'))
      ->setDescription(t('Number of missing documents checked.'))
      ->setRequired(FALSE);

    $properties['missing_found'] = DataDefinition::create('integer')
      ->setLabel(t('Missing found'))
      ->setDescription(t('Number of missing documents found.'))
      ->setRequired(FALSE);

    $properties['recorded_seq'] = DataDefinition::create('string')
      ->setLabel(t('Recorded sequence'))
      ->setDescription(t('Recorded intermediate sequence.'))
      ->setRequired(FALSE);

    $properties['session_id'] = DataDefinition::create('string')
      ->setLabel(t('Session ID'))
      ->setDescription(t('Unique session ID for the replication.'))
      ->setRequired(TRUE);

    $properties['start_time'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Start time'))
      ->setDescription(t('Date and time when replication started.'))
      ->setRequired(FALSE);

    $properties['end_time'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('End time'))
      ->setDescription(t('Date and time when replication ended.'))
      ->setRequired(FALSE);

    $fields['fail_info'] = DataDefinition::create('string_long')
      ->setLabel(t('Replication fail info'))
      ->setDescription(t('When a replication fails, it contains the info about the cause of the fail.'))
      ->setComputed(TRUE)
      ->setRequired(FALSE)
      ->setClass('\Drupal\replication\FailInfo');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'doc_write_failures' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
        'docs_read' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
        'docs_written' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
        'missing_checked' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
        'missing_found' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
        'session_id' => [
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'recorded_seq' => [
          'type' => 'varchar',
          'length' => 512,
          'not null' => TRUE,
        ],
        'start_last_seq' => [
          'type' => 'varchar',
          'length' => 512,
          'not null' => FALSE,
        ],
        'end_last_seq' => [
          'type' => 'varchar',
          'length' => 512,
          'not null' => FALSE,
        ],
        'start_time' => [
          'type' => 'varchar',
          'length' => 50,
          'not null' => FALSE,
        ],
        'end_time' => [
          'type' => 'varchar',
          'length' => 50,
          'not null' => FALSE,
        ],
      ],
    ];
  }

}

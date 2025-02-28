<?php

namespace Drupal\replication\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * The replication log entity type.
 *
 * @ContentEntityType(
 *   id = "replication_log",
 *   label = @Translation("Replication log"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *   },
 *   base_table = "replication_log",
 *   revision_table = "replication_log_revision",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "revision_id",
 *   },
 *   local = TRUE,
 * )
 */
class ReplicationLog extends ContentEntityBase implements ReplicationLogInterface {

  /**
   * {@inheritdoc}
   */
  public function getHistory() {
    return $this->get('history')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setHistory($history) {
    $this->set('history', $history);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSessionId() {
    return $this->get('session_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSessionId($session_id) {
    $this->set('session_id', $session_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLastSeq() {
    return $this->get('source_last_seq')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceLastSeq($source_last_seq) {
    $this->set('source_last_seq', $source_last_seq);
    return $this;
  }

  /**
   * @param $id string
   * @return \Drupal\replication\Entity\ReplicationLogInterface
   */
  public static function loadOrCreate($id) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('replication_log')
      ->loadByProperties(['uuid' => $id]);
    if (!empty($entities)) {
      return reset($entities);
    }
    else {
      return static::create(['uuid' => $id]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the replication log entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the replication log entity.'))
      ->setReadOnly(TRUE);

    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The local revision ID of the replication log entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['history'] = BaseFieldDefinition::create('replication_history')
      ->setLabel(t('Replication log history'))
      ->setDescription(t('The version id of the test entity.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['session_id'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('Replication session ID'))
      ->setDescription(t('The unique session ID of the last replication. Shortcut to the session_id in the last history item.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE);

    $fields['source_last_seq'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last processed checkpoint'))
      ->setDescription(t('The last processed checkpoint. Shortcut to the source_last_seq in the last history item.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE);

    $fields['ok'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('ok'))
      ->setDescription(t('Replication status'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setReadOnly(TRUE);

    return $fields;
  }
}

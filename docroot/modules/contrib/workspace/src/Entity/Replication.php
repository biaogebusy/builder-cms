<?php

namespace Drupal\workspace\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Drupal\workspace\WorkspacePointerInterface;

/**
 * Defines the Replication entity.
 *
 * @ingroup workspace
 *
 * @ContentEntityType(
 *   id = "replication",
 *   label = @Translation("Deployment"),
 *   base_table = "replication",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   local = TRUE,
 *   multiversion = FALSE
 * )
 */
class Replication extends ContentEntityBase implements ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {
  use EntityChangedTrait;

  const FAILED = -1;
  const QUEUED = 0;
  const REPLICATING = 1;
  const REPLICATED = 2;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Replication entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Replication entity.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user ID of person who started the deployment.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\workspace\Entity\Replication::getCurrentUserId');

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 512,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source'))
      ->setDescription(t('The source endpoint.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'workspace_pointer')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => -2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['target'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Target'))
      ->setDescription(t('The target endpoint.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'workspace_pointer')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => -1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['replicated'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Deployed'))
      ->setDescription(t('The time that the entity was deployed.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['replication_status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Replication status'))
      ->setDescription(t('The status of the replication.'))
      ->setRequired(TRUE)
      ->setDefaultValue(static::FAILED)
      ->setInitialValue(static::FAILED);

    $fields['fail_info'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Replication fail info'))
      ->setDescription(t('When a replication fails, it contains the info about the cause of the fail.'))
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setInitialValue('');

    $fields['archive_source'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Archive source workspace flag'))
      ->setDescription(t('The flag that marks if the source workspace should be archived if replication ends with success.'))
      ->setRequired(FALSE)
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE);

    $fields['doc_ids'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Selected entity UUIDs for deployment'))
      ->setDescription(t('This filed contains the list of UUIDs for changed entities selected for this replication.'))
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setInitialValue('')
      ->setSetting('case_sensitive', TRUE);

    return $fields;
  }

  /**
   * Sets the replication status to failed.
   */
  public function setReplicationStatusFailed() {
    $this->set('replication_status', static::FAILED);
    return $this;
  }

  /**
   * Sets the replication status to queued.
   */
  public function setReplicationStatusQueued() {
    $this->set('replication_status', static::QUEUED);
    return $this;
  }

  /**
   * Sets the replication status to replicating.
   */
  public function setReplicationStatusReplicating() {
    $this->set('replication_status', static::REPLICATING);
    return $this;
  }

  /**
   * Sets the replication status to replicated.
   */
  public function setReplicationStatusReplicated() {
    $this->set('replication_status', static::REPLICATED);
    return $this;
  }

  /**
   * Sets the fail info value.
   *
   * @param string $message
   *
   * @return \Drupal\workspace\Entity\Replication
   */
  public function setReplicationFailInfo($message) {
    $this->set('fail_info', $message);
    return $this;
  }

  /**
   * Gets the fail info value.
   */
  public function getReplicationFailInfo() {
    return $this->get('fail_info')->value;
  }

  /**
   * Gets the replication status value.
   */
  public function getReplicationStatus() {
    return $this->get('replication_status')->value;
  }

  /**
   * Sets the archive source flag.
   *
   * @param bool $archive
   *
   * @return \Drupal\workspace\Entity\Replication
   */
  public function setArchiveSource($archive = TRUE) {
    $this->set('archive_source', $archive);
    return $this;
  }

  /**
   * Gets the archive source flag.
   */
  public function getArchiveSource() {
    return $this->get('archive_source')->value;
  }

  /**
   * Sets the entity UUIDs selected for this replication.
   *
   * @param array $doc_ids
   *
   * @return \Drupal\workspace\Entity\Replication
   */
  public function setDocIds($doc_ids = []) {
    $serialized = Json::encode($doc_ids);
    $this->set('doc_ids', $serialized);
    return $this;
  }

  /**
   * Gets the list of UUIDs or empty string.
   *
   * @return array
   *   An array of UUIDs.
   */
  public function getDocIds() {
    return Json::decode($this->get('doc_ids')->value);
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Generates a replication ID.
   *
   * @param \Drupal\workspace\WorkspacePointerInterface $source
   *   The source workspace pointer.
   * @param \Drupal\workspace\WorkspacePointerInterface $target
   *   The target workspace pointer.
   *
   * @return string
   *   The replication ID.
   */
  public static function generateReplicationId(WorkspacePointerInterface $source, WorkspacePointerInterface $target) {
    return \md5(
      $source->getWorkspace()->getMachineName() .
      $target->getWorkspace()->getMachineName()
    );
  }

}

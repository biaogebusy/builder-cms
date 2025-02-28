<?php

namespace Drupal\workspace\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\replication\ReplicationTask\ReplicationTaskInterface;
use Drupal\workspace\WorkspacePointerInterface;

/**
 * Defines the Workspace pointer entity.
 *
 * @ingroup workspace
 *
 * @ContentEntityType(
 *   id = "workspace_pointer",
 *   label = @Translation("Workspace pointer"),
 *   base_table = "workspace_pointer",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *   },
 *   local = TRUE,
 *   multiversion = FALSE
 * )
 */
class WorkspacePointer extends ContentEntityBase implements WorkspacePointerInterface {
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
  }

  /**
   * @inheritDoc
   */
  public function label() {
    $label = parent::label();

    if (empty($label) && !empty($this->getWorkspace())) {
      $label = $this->getWorkspace()->label();
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkspace(WorkspaceInterface $workspace) {
    $this->set('workspace_pointer', $workspace->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkspaceId($workspace_id) {
    $this->set('workspace_pointer', $workspace_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkspace() {
    $ws = $this->get('workspace_pointer')->entity;
    return $ws;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkspaceId() {
    return $this->get('workspace_pointer')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkspaceAvailable($available = TRUE) {
    $this->set('workspace_available', $available);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkspaceAvailable() {
    return (bool) $this->get('workspace_available')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function generateReplicationId(WorkspacePointerInterface $target, ReplicationTaskInterface $task = NULL) {
    $request = \Drupal::request();
    $uuid = MD5($request->getHost() . $request->getPort());
    $source_name = $this->label();
    if ($this->getWorkspace() instanceof WorkspaceInterface) {
      $source_name = $this->getWorkspace()->getMachineName();
    }
    $target_name = $target->label();
    if ($target->getWorkspace() instanceof WorkspaceInterface) {
      $target_name = $target->getWorkspace()->getMachineName();
    }
    if ($task) {
      return \md5(
        $uuid .
        $source_name .
        $target_name .
        var_export($task->getDocIds(), TRUE) .
        ($task->getCreateTarget() ? '1' : '0') .
        ($task->getContinuous() ? '1' : '0') .
        $task->getFilter() .
        '' .
        $task->getStyle() .
        var_export($task->getHeartbeat(), TRUE)
      );
    }
    return \md5(
      $uuid .
      $source_name .
      $target_name
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Workspace pointer entity.'))
      ->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Workspace pointer entity.'))
      ->setReadOnly(TRUE);
    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The revision ID of the workspace pointer entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Workspace pointer entity.'))
      ->setSettings([
        'max_length' => 512,
        'text_processing' => 0,
      ])
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setRevisionable(TRUE);

    $fields['workspace_pointer'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Workspace'))
      ->setDescription(t('A reference to the workspace'))
      ->setSetting('target_type', 'workspace')
      ->setRevisionable(TRUE);

    // This field will keep the information about the availability of the
    // remote workspace, which is checked on every cron run. This value is not
    // used when doing the replication, because during replication the
    // availability of the remote workspaces is checked with HTTP requests.
    // This value is used to restrict the user to select from UI a remote
    // workspace as target when it is not available.
    $fields['workspace_available'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Workspace available'))
      ->setDescription(t('Keeps the availability of the referenced ' .
        'workspace, this flag might not be accurate, the availability should ' .
        'be checked regularly (it is checked on cron run).'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    return $fields;
  }

  /**
   * Load a workspace pointer for the given workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace entity to get the workspace pointer for.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The workspace pointer for the given workspace.
   */
  public static function loadFromWorkspace(WorkspaceInterface $workspace) {
    $pointers = \Drupal::service('entity_type.manager')->getStorage('workspace_pointer')->loadByProperties(['workspace_pointer' => $workspace->id()]);
    return reset($pointers);
  }

}

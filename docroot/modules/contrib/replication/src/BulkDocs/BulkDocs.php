<?php

namespace Drupal\replication\BulkDocs;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Error;
use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\Index\UuidIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

class BulkDocs implements BulkDocsInterface {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\multiversion\Entity\WorkspaceInterface
   */
  protected $workspace;

  /**
   * @var \Drupal\multiversion\Entity\Index\UuidIndexInterface
   */
  protected $uuidIndex;

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface
   */
  protected $revIndex;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Entity\ContentEntityInterface[]
   */
  protected $entities = [];

  /**
   * @var bool
   */
  protected $newEdits = TRUE;

  /**
   * @var array
   */
  protected $result = [];

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The replication settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   *
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   * @param \Drupal\multiversion\Entity\Index\UuidIndexInterface $uuid_index
   * @param \Drupal\multiversion\Entity\Index\RevisionIndexInterface $rev_index
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ImmutableConfig $config
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, WorkspaceInterface $workspace, UuidIndexInterface $uuid_index, RevisionIndexInterface $rev_index, EntityTypeManagerInterface $entity_type_manager, LockBackendInterface $lock, LoggerChannelInterface $logger, StateInterface $state, ImmutableConfig $config) {
    $this->workspaceManager = $workspace_manager;
    $this->workspace = $workspace;
    $this->uuidIndex = $uuid_index;
    $this->revIndex = $rev_index;
    $this->entityTypeManager = $entity_type_manager;
    $this->lock = $lock;
    $this->logger = $logger;
    $this->state = $state;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function newEdits($new_edits) {
    $this->newEdits = (bool) $new_edits;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntities($entities) {
    $this->entities = $entities;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities() {
    return $this->entities;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    // Writing a bulk of documents can potentially take a lot of time, so we
    // aquire a lock to ensure the integrity of the operation.
    do {
      // Check if the operation may be available.
      if ($this->lock->lockMayBeAvailable('bulk_docs')) {
        // The operation may be available, so break the wait and continue if we
        // successfully can acquire a lock.
        if ($this->lock->acquire('bulk_docs')) {
          break;
        }
      }
      $this->logger->critical('Lock exists on bulk operation. Waiting.');
    } while ($this->lock->wait('bulk_docs', 3000));

    $inital_workspace = $this->workspaceManager->getActiveWorkspace();
    $this->workspaceManager->setActiveWorkspace($this->workspace);

    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
    $this->state->set('comment.maintain_entity_statistics', FALSE);

    foreach ($this->entities as $entity) {
      $uuid = $entity->uuid();
      $rev = $entity->_rev->value;

      try {
        // Check if the revision being posted already exists.
        $record = $this->revIndex
          ->useWorkspace($this->workspace->id())
          ->get("$uuid:$rev");

        if ($record) {
          if (!$this->newEdits && !$record['is_stub']) {
            $this->result[] = [
              'error' => 'Conflict',
              'reason' => 'Document update conflict.',
              'id' => $uuid,
              'rev' => $rev,
            ];
            continue;
          }
        }

        // In cases where a stub was created earlier in the same bulk operation
        // it may already exists. This means we need to ensure the local ID
        // mapping is correct.
        $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
        $id_key = $entity_type->getKey('id');

        if ($record = $this->uuidIndex->useWorkspace($this->workspace->id())->get($entity->uuid())) {
          $entity->{$id_key}->value = $record['entity_id'];
          $entity->enforceIsNew(FALSE);
        }
        else {
          $entity->enforceIsNew(TRUE);
          $entity->{$id_key}->value = NULL;
        }

        $entity->workspace->target_id = $this->workspace->id();
        $entity->_rev->new_edit = $this->newEdits;
        $this->entityTypeManager->getStorage($entity->getEntityTypeId())->useWorkspace($this->workspace->id());
        if ($entity->save()) {
          $this->result[] = [
            'id' => $uuid,
            'ok' => TRUE,
            'rev' => $entity->_rev->value,
          ];
          if ($this->config->get('verbose_logging')) {
            $this->logger->info($entity_type->getLabel() . ' ' . $entity->label() . ' saved in workspace ' . $this->workspace->label());
          }
        }
      }
      catch (\Throwable $e) {
        $message = $e->getMessage();
        $this->result[] = [
          'error' => $message,
          'reason' => 'Exception',
          'id' => $uuid,
          'rev' => $entity->_rev->value,
        ];
        $arguments = Error::decodeException($e) + ['%uuid' => $uuid];
        $this->logger->error('%type: @message in %function (line %line of %file). The error occurred while saving the entity with the UUID: %uuid.', $arguments);
      }
    }

    // Enable the the maintenance of entity statistics for comments.
    $this->state->set('comment.maintain_entity_statistics', TRUE);

    // Switch back to the initial workspace.
    $this->workspaceManager->setActiveWorkspace($inital_workspace);

    $this->lock->release('bulk_docs');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResult() {
    return $this->result;
  }

}

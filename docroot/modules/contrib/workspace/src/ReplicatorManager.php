<?php

namespace Drupal\workspace;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\replication\Entity\ReplicationLog;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\replication\ReplicationTask\ReplicationTask;
use Drupal\replication\ReplicationTask\ReplicationTaskInterface;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Event\ReplicationEvent;
use Drupal\workspace\Event\ReplicationEvents;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the Replicator manager.
 */
class ReplicatorManager implements ReplicatorInterface {

  /**
   * The services available to perform replication.
   *
   * @var ReplicatorInterface[]
   */
  protected $replicators = [];

  /**
   * The injected service to track conflicts during replication.
   *
   * @var \Drupal\multiversion\Workspace\ConflictTrackerInterface
   */
  protected $conflictTracker;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The workspace replication queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The injected service to track conflicts during replication.
   *
   * @param \Drupal\multiversion\Workspace\ConflictTrackerInterface $conflict_tracker
   *   The confict tracking service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory.
   */
  public function __construct(ConflictTrackerInterface $conflict_tracker, EventDispatcherInterface $event_dispatcher, QueueFactory $queue) {
    $this->conflictTracker = $conflict_tracker;
    $this->eventDispatcher = $event_dispatcher;
    $this->queue = $queue->get('workspace_replication');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(WorkspacePointerInterface $source, WorkspacePointerInterface $target) {
    return TRUE;
  }

  /**
   * Adds replication services.
   *
   * @param ReplicatorInterface $replicator
   *   The service to make available for performing replication.
   */
  public function addReplicator(ReplicatorInterface $replicator) {
    $this->replicators[] = $replicator;
  }

  /**
   * {@inheritdoc}
   */
  public function replicate(WorkspacePointerInterface $source, WorkspacePointerInterface $target, $task = NULL, Replication $replication = NULL) {
    if ($replication === NULL) {
      // Create replication entity to Deploy changes from $source to $target.
      $replication = Replication::create([
        'name' => t('Deploy from @source to @target', ['@source' => $source->label(), '@target' => $target->label()]),
        'source' => $source,
        'target' => $target,
      ]);
    }

    // @todo Use here $replication->setDocIds() to set selected UUIDs when we'll
    // have implemented the way users can select changes for next deployment.

    // It is assumed a caller of replicate will set this static variable to
    // FALSE if they wish to proceed with replicating content upstream even in
    // the presence of conflicts. If the caller wants to make sure no conflicts
    // are replicated to the upstream, set this value to TRUE.
    // By default, the value is FALSE so as not to break the previous
    // behavior.
    // @todo Use a sequence index instead of boolean? This will allow the
    // caller to know there haven't been additional conflicts.
    $is_aborted_on_conflict = drupal_static('workspace_is_aborted_on_conflict', FALSE);

    // Abort updating the Workspace if there are conflicts.
    $initial_conflicts = $this->conflictTracker->useWorkspace($source->getWorkspace())->getAll();
    if ($is_aborted_on_conflict && $initial_conflicts) {
      return $this->replicationLog($source, $target, $task);
    }

    // Derive a pull replication task from the Workspace we are acting on.
    $pull_task = $this->getTask($source->getWorkspace(), 'pull_replication_settings');

    // Set selected for deployment UUIDs.
    $doc_ids = $replication->getDocIds();
    if (is_array($doc_ids) && !empty($doc_ids)) {
      $pull_task->setFilter('_doc_ids');
      $pull_task->setDocIds($doc_ids);
    }

    // Pull in changes from $target to $source to ensure a merge will complete.
    $this->update($target, $source, $pull_task);

    // Automatically derive settings from the workspace if no task sent.
    // @todo Refactor to eliminate obscurity of having an optional parameter
    // and automatically setting the parameter's value.
    if ($task === NULL) {
      // Derive a push replication task from the Workspace we are acting on.
      $task = $this->getTask($source->getWorkspace(), 'push_replication_settings');
    }

    // Set selected for deployment UUIDs.
    if (is_array($doc_ids) && !empty($doc_ids)) {
      $task->setFilter('_doc_ids');
      $task->setDocIds($doc_ids);
    }

    // Push changes from $source to $target.
    $this->queueReplication($replication, $task);

    return $this->replicationLog($source, $target, $task, TRUE);
  }

  /**
   * Derives a replication task from an entity with replication settings.
   *
   * This can be used with a Workspace using the 'push_replication_settings'
   * and 'pull_replication_settings' fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to derive the replication task from.
   * @param string $field_name
   *   The field name that references a ReplicationSettings config entity.
   *
   * @return \Drupal\replication\ReplicationTask\ReplicationTaskInterface
   *   A replication task that can be passed to a replicator.
   *
   * @throws \Symfony\Component\Console\Exception\LogicException
   *   The replication settings field does not exist on the entity.
   */
  public function getTask(EntityInterface $entity, $field_name) {
    $task = new ReplicationTask();
    $items = $entity->get($field_name);

    if (!$items instanceof EntityReferenceFieldItemListInterface) {
      throw new LogicException('Replication settings field does not exist.');
    }

    $referenced_entities = $items->referencedEntities();
    if (count($referenced_entities) > 0) {
      $task->setFilter($referenced_entities[0]->getFilterId());
      $task->setParameters($referenced_entities[0]->getParameters());
    }

    return $task;
  }

  /**
   * Update the target using the source before doing a replication.
   *
   * This is used primarily as a public facing method by the UpdateForm. It
   * avoids the additional logic found in the replicate method.
   *
   * @param \Drupal\workspace\WorkspacePointerInterface $target
   *   The workspace to replicate to.
   * @param \Drupal\workspace\WorkspacePointerInterface $source
   *   The workspace to replicate from.
   * @param mixed $task
   *   Optional information that defines the replication task to perform.
   *
   * @return \Drupal\replication\Entity\ReplicationLogInterface
   *   The log entry for this replication.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function update(WorkspacePointerInterface $target, WorkspacePointerInterface $source, $task = NULL) {
    // Create replication entity.
    // For an update (pull) the source and target are reversed.
    $replication = Replication::create([
      'name' => t('Update from @source to @target', ['@source' => $target->label(), '@target' => $source->label()]),
      'source' => $target,
      'target' => $source,
    ]);

    if ($task && is_array($task->getDocIds())) {
      $replication->setDocIds($task->getDocIds());
    }

    $this->queueReplication($replication, $task);

    return $this->replicationLog($target, $source, $task, TRUE);
  }

  /**
   * Internal method to contain replication logic.
   *
   * @param \Drupal\workspace\Entity\Replication $replication
   *   The replication.
   * @param mixed $task
   *   Optional information that defines the replication task to perform.
   *
   * @return \Drupal\replication\Entity\ReplicationLogInterface
   *   The log entry for this replication.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function doReplication(Replication $replication, $task = NULL) {
    $source = $replication->get('source')->entity;
    $target = $replication->get('target')->entity;
    foreach ($this->replicators as $replicator) {
      if ($replicator->applies($source, $target)) {
        // Do the mysterious dance of replication...
        $log = $replicator->replicate($source, $target, $task);

        $doc_ids_exist = FALSE;
        // If there are doc IDs specified in the task, we don't set a new
        // "last_sequence.workspace.WORKSPACE_ID" state. That because we'll need
        // to get changes from the previous value of that state when replicating
        // selective content.
        if (!empty($task) && !empty($task->getDocIds())) {
          $doc_ids_exist = TRUE;
        }
        if ($log instanceof ReplicationLogInterface
          && $log->get('ok')->value == TRUE
          && isset($log->workspace->target_id)
          && !$doc_ids_exist) {
          \Drupal::state()->set('last_sequence.workspace.' . $log->workspace->target_id, $log->source_last_seq->value);
        };

        return $log;
      }
    }

    return $this->replicationLog($source, $target, $task);
  }

  /**
   * Generate a failed replication log and return it.
   *
   * @param \Drupal\workspace\WorkspacePointerInterface $source
   *   The workspace to replicate from.
   * @param \Drupal\workspace\WorkspacePointerInterface $target
   *   The workspace to replicate to.
   * @param \Drupal\replication\ReplicationTask\ReplicationTaskInterface|null $task
   *   The replication task.
   * @param bool $ok
   *   True if the replication was started successfully, false otherwise.
   *
   * @return \Drupal\replication\Entity\ReplicationLogInterface
   *   The log entry for this replication.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function replicationLog(WorkspacePointerInterface $source, WorkspacePointerInterface $target, ReplicationTaskInterface $task = NULL, $ok = FALSE) {
    $time = new \DateTime();
    $history = [
      'start_time' => $time->format('D, d M Y H:i:s e'),
      'end_time' => $time->format('D, d M Y H:i:s e'),
      'session_id' => \md5((\microtime(TRUE) * 1000000)),
    ];
    $replication_log_id = $source->generateReplicationId($target, $task);
    /** @var \Drupal\replication\Entity\ReplicationLogInterface $replication_log */
    $replication_log = ReplicationLog::loadOrCreate($replication_log_id);
    $replication_log->set('ok', $ok);
    $replication_log->setSessionId($history['session_id']);
    $replication_log->setHistory($history);
    $replication_log->save();
    return $replication_log;
  }

  /**
   * Queue a new replication.
   *
   * @param \Drupal\workspace\Entity\Replication $replication
   *   The replication.
   * @param \Drupal\replication\ReplicationTask\ReplicationTaskInterface $task
   *   The replication task.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected  function queueReplication(Replication $replication, ReplicationTaskInterface $task) {
    $replication->setReplicationStatusQueued();
    $replication->save();

    $this->queue->createItem([
      'task' => $task,
      'replication' => $replication,
    ]);

    $this->eventDispatcher->dispatch(ReplicationEvents::QUEUED_REPLICATION, new ReplicationEvent($replication));
  }

}

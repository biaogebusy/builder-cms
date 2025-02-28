<?php

namespace Drupal\workspace\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\workbench_moderation\Event\WorkbenchModerationEvents;
use Drupal\workbench_moderation\Event\WorkbenchModerationTransitionEvent;
use Drupal\workspace\ReplicatorManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for workbench transitions.
 */
class WorkbenchModerationSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager to use for checking moderation information.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The replicator manager to trigger replication on.
   *
   * @var \Drupal\workspace\ReplicatorManager
   */
  protected $replicatorManager;

  /**
   * Inject dependencies.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager to use for checking moderation information.
   * @param ReplicatorManager $replicator_manager
   *   The replicator manager to trigger replication on.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ReplicatorManager $replicator_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->replicatorManager = $replicator_manager;
  }

  /**
   * Listener for workbench moderation event transitions.
   *
   * @param \Drupal\workbench_moderation\Event\WorkbenchModerationTransitionEvent $event
   *   The transition event that just fired.
   */
  public function onTransition(WorkbenchModerationTransitionEvent $event) {
    /* @var WorkspaceInterface $entity */
    $entity = $event->getEntity();

    if ($entity->getEntityTypeId() == 'workspace' && $this->wasDefaultRevision($event)) {

      // If there is no upstream to replicate to, abort.
      if (!$entity->get('upstream')->entity) {
        drupal_set_message(t('The :source workspace does not have an upstream to replicate to!', [
          ':source' => $entity->label(),
        ]), 'error');

        // @todo Should we revert the workspace to its previous state?

        return;
      }

      $log = $this->mergeWorkspaceToParent($entity);

      // Pass the replication status to the logic that triggered the state
      // change. This allows, for example, the caller to revert back the
      // Workspace's workflow state.
      // @see \Drupal\workspace\Entity\Form\WorkspaceForm
      drupal_static('publish_workspace_replication_status', (bool) $log->get('ok')->value);

      // Set the previous workflow state in case a revert needs to happen.
      // Note: we would not be able to revert back the Workspace's moderation
      // state here since the event is triggered within a presave hook.
      // @todo Find a way to share the replication pass/fail status besides a static.
      drupal_static('publish_workspace_previous_state', $event->getStateBefore());
    }
  }

  /**
   * Determines if the transition is to a "default revision" state.
   *
   * @param \Drupal\workbench_moderation\Event\WorkbenchModerationTransitionEvent $event
   *   The transition event.
   *
   * @return bool
   *   TRUE if the event is moving an entity to a default-revision state.
   */
  protected function wasDefaultRevision(WorkbenchModerationTransitionEvent $event) {
    /** @var Drupal\workbench_moderation\Entity\ModerationState $post_state */
    $post_state = $this->entityTypeManager->getStorage('moderation_state')->load($event->getStateAfter());

    return $post_state->isPublishedState();
  }

  /**
   * Merges a workspace to its parent workspace, if any.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace entity to merge.
   *
   * @return \Drupal\replication\Entity\ReplicationLog
   *   The replication log entry.
   */
  protected function mergeWorkspaceToParent(WorkspaceInterface $workspace) {
    /* @var \Drupal\workspace\WorkspacePointerInterface $parent_workspace */
    $parent_workspace_pointer = $workspace->get('upstream')->entity;

    /* @var \Drupal\workspace\WorkspacePointerInterface $source_pointer */
    $source_pointer = $this->getPointerToWorkspace($workspace);

    // Derive a replication task from the Workspace we are acting on.
    $task = $this->replicatorManager->getTask($workspace, 'push_replication_settings');

    return $this->replicatorManager->replicate($source_pointer, $parent_workspace_pointer, $task);
  }

  /**
   * Returns a pointer to the specified workspace.
   *
   * In most cases this pointer will be unique, but that is not guaranteed
   * by the schema. If there are multiple pointers, which one is returned is
   * undefined.
   *
   * @todo Move this to somewhere more logical and globally accessible.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace for which we want a pointer.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The pointer to the provided workspace.
   */
  protected function getPointerToWorkspace(WorkspaceInterface $workspace) {
    $pointers = $this->entityTypeManager
      ->getStorage('workspace_pointer')
      ->loadByProperties(['workspace_pointer' => $workspace->id()]);
    $pointer = reset($pointers);
    return $pointer;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    if (class_exists(WorkbenchModerationEvents::class)) {
      $events[WorkbenchModerationEvents::STATE_TRANSITION][] = ['onTransition'];
    }
    return $events;
  }

}

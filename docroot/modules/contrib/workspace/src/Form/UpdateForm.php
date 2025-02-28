<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\workspace\ReplicatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form to update the current workspace with its upstream.
 */
class UpdateForm extends ConfirmFormBase {

  /**
   * The workspace manager.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The replicator manager.
   *
   * @var \Drupal\workspace\ReplicatorInterface
   */
  protected $replicatorManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The injected service to track conflicts during replication.
   *
   * @var \Drupal\multiversion\Workspace\ConflictTrackerInterface
   */
  protected $conflictTracker;

  /**
   * Inject services needed by the form.
   *
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\workspace\ReplicatorInterface $replicator_manager
   *   The replicator manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\multiversion\Workspace\ConflictTrackerInterface $conflict_tracker
   *   The conflict tracking service.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, EntityTypeManagerInterface $entity_type_manager, ReplicatorInterface $replicator_manager, RendererInterface $renderer, ConflictTrackerInterface $conflict_tracker) {
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->replicatorManager = $replicator_manager;
    $this->renderer = $renderer;
    $this->conflictTracker = $conflict_tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspace.manager'),
      $container->get('entity_type.manager'),
      $container->get('workspace.replicator_manager'),
      $container->get('renderer'),
      $container->get('workspace.conflict_tracker')
    );
  }

  /**
   * Get the current active workspace's pointer.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The active workspace.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getActive() {
    /** @var \Drupal\multiversion\Entity\WorkspaceInterface $workspace */
    $workspace = $this->workspaceManager->getActiveWorkspace();
    /** @var \Drupal\workspace\WorkspacePointerInterface[] $pointers */
    $pointers = $this->entityTypeManager
      ->getStorage('workspace_pointer')
      ->loadByProperties(['workspace_pointer' => $workspace->id()]);
    return reset($pointers);
  }

  /**
   * Returns the upstream for the given workspace.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   *   The upstream workspace.
   */
  protected function getUpstream() {
    $workspace = $this->workspaceManager->getActiveWorkspace();
    if (isset($workspace->upstream)) {
      return $workspace->upstream->entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#ajax'] = [
      'callback' => [$this, 'update'],
      'event' => 'mousedown',
      'prevent' => 'click',
      'progress' => [
        'type' => 'throbber',
        'message' => 'Updating',
      ],
    ];

    if (!$this->getUpstream()) {
      unset($form['actions']['submit']);
    }
    unset($form['actions']['cancel']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Queue an update of @workspace', ['@workspace' => $this->getActive()->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $upstream = $this->getUpstream();
    $active = $this->getActive();
    try {
      // Derive a replication task from the Workspace we are acting on.
      $task = $this->replicatorManager->getTask($active->getWorkspace(), 'pull_replication_settings');

      $response = $this->replicatorManager->update($upstream, $active, $task);

      if (($response instanceof ReplicationLogInterface) && ($response->get('ok')->value == TRUE)) {
        // Notify the user if there are now conflicts.
        $conflicts = $this->conflictTracker
          ->useWorkspace($active->getWorkspace())
          ->getAll();

        if ($conflicts) {
          drupal_set_message($this->t(
            '%workspace has been updated with content from %upstream, but there are <a href=":link">@count conflict(s) with the %target workspace</a>.',
            [
              '%upstream' => $upstream->label(),
              '%workspace' => $active->label(),
              ':link' => Url::fromRoute('entity.workspace.conflicts', ['workspace' => $active->getWorkspace()->id()])->toString(),
              '@count' => count($conflicts),
              '%target' => $upstream->label(),
            ]
          ), 'error');
        }
        else {
          drupal_set_message($this->t('An update of %workspace has been queued with content from %upstream.', ['%upstream' => $upstream->label(), '%workspace' => $active->label()]));
          if (\Drupal::moduleHandler()->moduleExists('deploy')) {
            $input = $form_state->getUserInput();
            if (!isset($input['_drupal_ajax'])) {
              $form_state->setRedirect('entity.replication.collection');
            }
          }
        }
      }
      else {
        drupal_set_message($this->t('Error updating %workspace from %upstream.', ['%upstream' => $upstream->label(), '%workspace' => $active->label()]), 'error');
      }
    }
    catch (\Exception $e) {
      watchdog_exception('Workspace', $e);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Callback handler for the update form button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state data.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function update(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    if (\Drupal::moduleHandler()->moduleExists('deploy')) {
      $response->addCommand(new RedirectCommand(Url::fromRoute('entity.replication.collection')->setAbsolute()->toString()));
    }
    else {
      $status_messages = ['#type' => 'status_messages'];
      $response->addCommand(new PrependCommand('.region-highlighted', $this->renderer->renderRoot($status_messages)));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if (!$this->getUpstream()) {
      return $this->t('%workspace has no upstream set.', ['%workspace' => $this->getActive()->label()]);
    }
    return $this->t('Do you want to queue %workspace to be updated with changes from %upstream?', ['%upstream' => $this->getUpstream()->label(), '%workspace' => $this->getActive()->label()]);
  }

}

<?php

namespace Drupal\workspace\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the workspace edit forms.
 */
class WorkspaceForm extends ContentEntityForm {

  /**
   * The injected service to track conflicts during replication.
   *
   * @var ConflictTrackerInterface
   */
  protected $conflictTracker;

  /**
   * The workspace content entity.
   *
   * @var \Drupal\multiversion\Entity\WorkspaceInterface
   */
  protected $entity;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   * @param ConflictTrackerInterface $conflict_tracker
   *   The conflict tracking service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *
   * @todo Add TimeInterface as parameter for constructor and use the value in
   * parent constructor starting with Drupal 8.4.0.
   */
  public function __construct(EntityManagerInterface $entity_manager, ConflictTrackerInterface $conflict_tracker, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($entity_manager, $entity_type_bundle_info);
    $this->conflictTracker = $conflict_tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('workspace.conflict_tracker'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $workspace = $this->entity;

    if ($this->operation == 'edit') {
      // Allow the user to not abort on conflicts.
      $this->conflictTracker->useWorkspace($workspace);
      $conflicts = $this->conflictTracker->getAll();
      if ($conflicts) {
        $form['message'] = $this->generateMessageRenderArray('error', $this->t(
          'There are <a href=":link">@count conflict(s) with the :target workspace</a>. Pushing changes to :target may result in unexpected behavior or data loss, and cannot be undone. Please proceed with caution.',
          [
            '@count' => count($conflicts),
            ':link' => Url::fromRoute('entity.workspace.conflicts', ['workspace' => $workspace->id()])->toString(),
            ':target' => $workspace->get('upstream')->entity ? $workspace->get('upstream')->entity->label() : '',
          ]
        ));
        $form['is_aborted_on_conflict'] = [
          '#type' => 'radios',
          '#title' => $this->t('Abort if there are conflicts?'),
          '#default_value' => 'true',
          '#options' => [
            'true' => $this->t('Yes, if conflicts are found do not replicate to upstream.'),
            'false' => $this->t('No, go ahead and push any conflicts to the upstream.'),
          ],
          '#weight' => 0,
        ];
      }
      else {
        $form['message'] = $this->generateMessageRenderArray('status', 'There are no conflicts.');
      }

      // Set the form title based on workspace.
      $form['#title'] = $this->t('Edit workspace %label', ['%label' => $workspace->label()]);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 128,
      '#default_value' => $workspace->label(),
      '#description' => $this->t("Label for the Workspace."),
      '#required' => TRUE,
      '#weight' => 1,
    ];

    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#required' => TRUE,
      '#title' => $this->t('Workspace Machine Name'),
      '#maxlength' => 128,
      '#default_value' => $workspace->get('machine_name')->value,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
    ];

    return parent::form($form, $form_state, $workspace);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditedFieldNames(FormStateInterface $form_state) {
    return array_merge([
      'label',
      'machine_name',
    ], parent::getEditedFieldNames($form_state));
  }

  /**
   * {@inheritdoc}
   */
  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // Manually flag violations of fields not handled by the form display. This
    // is necessary as entity form displays only flag violations for fields
    // contained in the display.
    $field_names = [
      'label',
      'machine_name',
    ];
    foreach ($violations->getByFields($field_names) as $violation) {
      list($field_name) = explode('.', $violation->getPropertyPath(), 2);
      $form_state->setErrorByName($field_name, $violation->getMessage());
    }
    parent::flagViolations($violations, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Pass the abort flag to the ReplicationManager using runtime-only state,
    // i.e. a static.
    // @see \Drupal\workspace\ReplicatorManager
    $is_aborted_on_conflict = !$form_state->hasValue('is_aborted_on_conflict') || $form_state->getValue('is_aborted_on_conflict') === 'true';
    drupal_static('workspace_is_aborted_on_conflict', $is_aborted_on_conflict);

    $workspace = $this->entity;
    $is_new = $workspace->isNew();
    $workspace->save();

    $info = ['%info' => $workspace->label()];
    $context = ['@type' => $workspace->bundle(), '%info' => $workspace->label()];
    $logger = $this->logger('workspace');

    // If Workbench Moderation is enabled, a publish of the Workspace should
    // trigger a replication. We pass back the status of that replication using
    // a static variable. If replication happened, we want to handle the case
    // of failed replication, as well as modify the wording of the saved
    // message.
    // @see \Drupal\workspace\EventSubscriber\WorkbenchModerationSubscriber
    // @todo Avoid using statics.
    $replication_status = drupal_static('publish_workspace_replication_status', NULL);
    if ($replication_status !== NULL) {
      $logger->notice('@type: updated %info.', $context);

      if ($replication_status == TRUE) {
        // The replication succeeded, in addition to saving the workspace.
        drupal_set_message($this->t('Workspace :source has been updated and changes were pushed to :target.', [
          ':source' => $workspace->label(),
          ':target' => $workspace->get('upstream')->entity->label(),
        ]), 'status');

        $form_state->setValue('id', $workspace->id());
        $form_state->set('id', $workspace->id());
        $redirect = $this->currentUser()->hasPermission('administer workspaces') ? $workspace->toUrl('collection') : $workspace->toUrl('canonical');
        $form_state->setRedirectUrl($redirect);
      }
      else {
        // The replication failed, even though the Workspace was updated.
        $previous_workflow_state = drupal_static('publish_workspace_previous_state', NULL);

        // This variable should always be set, else there is an issue with
        // the trigger logic.
        if ($previous_workflow_state === NULL) {
          throw new \Exception('The publish_workspace_replication_status should be set.');
        }

        // Revert the workspace back to its previous moderation state.
        $workspace->moderation_state->target_id = $previous_workflow_state;
        $workspace->save();

        // Show the form again.
        $form_state->setRebuild();
      }
    }
    else {
      // Assume a replication did not happen OR that Workbench Moderation is not
      // installed.
      if ($is_new) {
        $logger->notice('@type: added %info.', $context);
        drupal_set_message($this->t('Workspace %info has been created.', $info));
      }
      else {
        $logger->notice('@type: updated %info.', $context);
        drupal_set_message($this->t('Workspace %info has been updated.', $info));
      }

      if ($workspace->id()) {
        $form_state->setValue('id', $workspace->id());
        $form_state->set('id', $workspace->id());
        $redirect = $this->currentUser()->hasPermission('administer workspaces') ? $workspace->toUrl('collection') : $workspace->toUrl('canonical');
        $form_state->setRedirectUrl($redirect);
      }
      else {
        drupal_set_message($this->t('The workspace could not be saved.'), 'error');
        $form_state->setRebuild();
      }
    }
  }

  /**
   * Generate a message render array with the given text.
   *
   * @param string $type
   *   The type of message: status, warning, or error.
   * @param string $message
   *   The message to create with.
   *
   * @return array
   *   The render array for a status message.
   *
   * @see \Drupal\Core\Render\Element\StatusMessages
   */
  protected function generateMessageRenderArray($type, $message) {
    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        $type => [Markup::create($message)],
      ],
    ];
  }

  /**
   * Determines if a workspace with the specified machine name already exists.
   *
   * @param string $machine_name
   *
   * @return bool
   *   TRUE if the machine_name exists, FALSE otherwise.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exists($machine_name) {
    return (bool) $this->entityTypeManager
      ->getStorage('workspace')
      ->getQuery()
      ->condition('machine_name', $machine_name)
      ->execute();
  }

}

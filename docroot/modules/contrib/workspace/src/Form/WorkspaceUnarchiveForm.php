<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle workspace unarchive on administrative pages.
 */
class WorkspaceUnarchiveForm extends ConfirmFormBase {

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace to be unarchived.
   *
   * @var \Drupal\multiversion\Entity\WorkspaceInterface
   */
  protected $workspace;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspace.manager'),
      $container->get('entity_type.manager')
    );
  }

  public function __construct(WorkspaceManagerInterface $workspace_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unarchive');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action will unarchive the %workspace workspace.', ['%workspace' => $this->workspace->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_unarchive';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, WorkspaceInterface $workspace = NULL) {
    $this->workspace = $workspace;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->workspace->isPublished()) {
      $form_state->setErrorByName('', 'This workspace is not archived.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to unarchive the %workspace workspace?', ['%workspace' => $this->workspace->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.workspace.archived');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->workspace->setPublished()->save();
      $form_state->setRedirect('entity.workspace.collection');
    }
    catch(\Exception $e) {
      watchdog_exception('Workspace', $e);
      $this->messenger()->addError($e->getMessage());
    }
    $this->messenger()->addStatus($this->t('The %workspace workspace has been successfully unarchived.', ['%workspace' => $this->workspace->label()]));
  }
}

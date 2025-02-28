<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for forms that activate a workspace.
 *
 * Use this class as the base for any form that switches the active workspace.
 * This abstraction handles form validation and submission.
 */
abstract class WorkspaceActivateFormBase extends FormBase {

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getValue('workspace_id');

    // Ensure we are given an ID.
    if (!$id) {
      $form_state->setErrorByName('workspace_id', 'The workspace ID is required.');
    }

    // Ensure the workspace by that id exists.
    /** @var WorkspaceInterface $workspace */
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($id);
    if (!$workspace) {
      $form_state->setErrorByName('workspace_id', 'This workspace no longer exists.');
    }
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getValue('workspace_id');
    /** @var WorkspaceInterface $workspace */
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($id);

    try {
      $this->workspaceManager->setActiveWorkspace($workspace);
      $form_state->setRedirect('<front>');
    }
    catch(\Exception $e) {
      watchdog_exception('Workspace', $e);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

}

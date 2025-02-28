<?php

namespace Drupal\workspace\Entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityDeleteFormTrait;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting a workspace entity.
 */
class WorkspaceDeleteForm extends ContentEntityConfirmFormBase {

  use EntityDeleteFormTrait;
  use MessengerTrait;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  private $workspaceManager;

  /**
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  private $defaultWorkspace;

  /**
   * WorkspaceDeleteForm constructor.
   *
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $default_workspace_id
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, $default_workspace_id, EntityManagerInterface $entity_manager, EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL) {
    if (floatval(\Drupal::VERSION) >= 8.6) {
      parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    }
    else {
      parent::__construct($entity_manager, $entity_type_bundle_info, $time);
    }
    $this->workspaceManager = $workspace_manager;
    $this->defaultWorkspace = Workspace::load($default_workspace_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspace.manager'),
      $container->getParameter('workspace.default'),
      $container->get('entity.manager'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $this->getEntity()->id();
    $active_id = $this->workspaceManager->getActiveWorkspace();
    $default_id = $this->defaultWorkspace->id();
    if (in_array($id, [$active_id, $default_id])) {
      $this->messenger()->addError($this->t('It is not possible to delete the active or default workspaces.'));
    }
    else {
      $this->entity->delete();

      $this->messenger()->addMessage(
        $this->t('Workspace @label and all the content from that workspace have been queued for deletion. They will be deleted on next cron run.',
          [
            '@label' => $this->entity->label()
          ]
        )
      );
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}

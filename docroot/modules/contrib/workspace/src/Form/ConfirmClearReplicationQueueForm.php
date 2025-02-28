<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\workspace\Entity\Replication;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfirmClearReplicationQueueForm.
 *
 * @package Drupal\replication\Form
 */
class ConfirmClearReplicationQueueForm extends ConfirmFormBase {


  /**
   * The entity type storage service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'confirm_clear_replication_queue_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->clearReplicationQueue()) {
      $message = $this->t('All the queued deployments have been marked as failed and have been removed from the replication queue.');
    }
    else {
      $message = $this->t('There were not any queued deployments in the replication queue.');
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
    $this->messenger()->addMessage($message);
  }

  /**
   * Clears the replication queue.
   *
   * @see \Drupal\workspace\Plugin\QueueWorker\WorkspaceReplication::processItem().
   */
  public function clearReplicationQueue() {
    // We actually don't clear the replication queue, we just mark as failed all
    // the queued replications - this will allow, during the queue item
    // processing to not do any actual processing for an item that contains the
    // failed replication, it will just go out the queue without doing anything
    // with it.
    $queued_items_deleted = FALSE;
    /** @var Replication[] $queued_replications */
    $queued_replications = $this->entityTypeManager
      ->getStorage('replication')
      ->loadByProperties(['replication_status' => Replication::QUEUED]);
    foreach ($queued_replications as $replication) {
      $replication
        ->setReplicationStatusFailed()
        ->setReplicationFailInfo($this->t('This deployment has been marked as failed manually, when clearing the replication queue.'))
        ->save();
      $queued_items_deleted = TRUE;
    }
    return $queued_items_deleted;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('All replications will be marked as failed and removed from the cron queue, except those that are in progress. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear the replication queue?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('replication.settings_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Clear queue');
  }

}

<?php

namespace Drupal\advancedqueue\Form;

use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsDeletingJobsInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for deleting a job.
 */
class DeleteJob extends ConfirmFormBase {

  /**
   * The queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $queue;

  /**
   * The job ID to delete.
   *
   * @var int
   */
  protected $jobId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advancedqueue_delete_job';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete job @job_id from the @queue queue?', [
      '@job_id' => $this->jobId,
      '@queue' => $this->queue->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('view.advancedqueue_jobs.page_1', ['arg_0' => $this->queue->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, QueueInterface $advancedqueue_queue = NULL, $job_id = NULL) {
    $this->queue = $advancedqueue_queue;
    $this->jobId = $job_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queue_backend = $this->queue->getBackend();
    if ($queue_backend instanceof SupportsDeletingJobsInterface) {
      $queue_backend->deleteJob($this->jobId);
    }

    $this->messenger()->addStatus($this->t('Job @job_id has been deleted.', [
      '@job_id' => $this->jobId,
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

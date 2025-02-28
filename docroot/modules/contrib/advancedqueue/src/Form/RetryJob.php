<?php

namespace Drupal\advancedqueue\Form;

use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for retrying a job.
 */
class RetryJob extends ConfirmFormBase {

  /**
   * The queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $queue;

  /**
   * The job ID to retry.
   *
   * @var int
   */
  protected $jobId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advancedqueue_retry_job';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to retry job @job_id from the @queue queue?', [
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
    $job = $queue_backend->loadJob($this->jobId);
    $queue_backend->retryJob($job);

    $this->messenger()->addStatus($this->t('Job @job_id has been queued for retrying.', [
      '@job_id' => $this->jobId,
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

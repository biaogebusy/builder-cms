<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\queue_ui\QueueInfoTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for configuring cron settings for a specific queue.
 */
class CronForm extends FormBase {

  use QueueInfoTrait;

  /**
   * Constructor for initializing the class with dependencies.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   Manager for handling queue workers.
   * @param \Drupal\Core\State\StateInterface $state
   *   Interface for managing state.
   */
  public function __construct(
    protected readonly QueueWorkerManagerInterface $queueWorkerManager,
    protected readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.queue_worker'),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cron_form';
  }

  /**
   * Gets the title of a given queue.
   *
   * @param string $queue
   *   The identifier of the queue.
   *
   * @return string
   *   The title of the queue.
   */
  public function title(string $queue): string {
    $queue_definition = $this->queueWorkerManager->getDefinition($queue);
    return $this->getQueueTitle($queue_definition);
  }

  /**
   * Determines access based on the existence of a queue definition.
   *
   * @param string $queue
   *   The name of the queue to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result object which will determine if access is prohibited.
   */
  public function access(string $queue): AccessResultInterface {
    return AccessResult::allowedIf(
      $this->queueWorkerManager->hasDefinition($queue)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $queue = NULL) {
    $queue_definition = $this->queueWorkerManager->getDefinition($queue);
    $form['cron'] = [
      '#type' => 'number',
      '#title' => $this->t('Cron Time Limit'),
      '#placeholder' => $this->t('Cron disabled'),
      '#default_value' => ($queue_definition['cron']['time'] ?? ''),
      '#attributes' => [
        'min' => 0,
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $cron_value = $form_state->getValue('cron', '');
    if ($cron_value !== '' && !is_numeric($cron_value)) {
      $form_state->setErrorByName('cron', $this->t('Cron value must be a number'));
    }
    $limit = (int) $cron_value;
    if ($limit < 0) {
      $form_state->setErrorByName('cron', $this->t('Cron value cannot be less than 0'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queue = $form_state->getBuildInfo()['args'][0];
    $this->state->set('queue_ui_cron_' . $queue, $form_state->getValue('cron'));
    $this->messenger()->addStatus($this->t(
      'Cron Time Limit updated for: @queue [@id]',
      [
        '@queue' => $this->getQueueFullTitle(
          $this->queueWorkerManager->getDefinition($queue)
        ),
        '@id' => $queue,
      ]
    ));
    // Clear the cached plugin definition so that changes come into effect.
    $this->queueWorkerManager->clearCachedDefinitions();

  }

}

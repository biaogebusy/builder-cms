<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Url;
use Drupal\queue_ui\QueueUIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfirmItemReleaseForm declaration.
 *
 * @package Drupal\queue_ui\Form
 * @phpstan-consistent-constructor
 */
class ConfirmItemReleaseForm extends ConfirmFormBase {

  /**
   * The queue name.
   */
  protected string $queueName;

  /**
   * The queue item.
   */
  protected string $queueItem;

  /**
   * ConfirmItemReleaseForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   The queue worker manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    private QueueUIManager $queueUIManager,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.manager.queue_ui'),
      $container->get('plugin.manager.queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t(
      'Are you sure you want to release item %queueItem of %queueName queue?',
      [
        '%queueItem' => $this->queueItem,
        '%queueName' => $this->queueWorkerManager->getDefinition(
          $this->queueName
        )['title'],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone and will force the release of the item even if it is currently being processed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('queue_ui.inspect', ['queueName' => $this->queueName]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_confirm_item_release_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form is where the settings form is being included.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param bool $queueName
   *   The name of the queue being inspected.
   * @param bool $queueItem
   *   The queue item.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $queueName = FALSE, $queueItem = FALSE) {
    $this->queueName = $queueName;
    $this->queueItem = $queueItem;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queue_ui = $this->queueUIManager->fromQueueName($this->queueName);
    $queue_ui->releaseItem($this->queueItem);

    $this->messenger->addMessage("Released queue item " . $this->queueItem);
    $form_state->setRedirectUrl(Url::fromRoute('queue_ui.inspect', ['queueName' => $this->queueName]));
  }

}

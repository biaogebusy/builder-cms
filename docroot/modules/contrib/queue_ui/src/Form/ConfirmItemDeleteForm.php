<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\queue_ui\QueueUIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfirmItemDeleteForm declaration.
 *
 * @package Drupal\queue_ui\Form
 * @phpstan-consistent-constructor
 */
class ConfirmItemDeleteForm extends ConfirmFormBase {

  /**
   * The queue name.
   *
   * @var string
   */
  protected $queueName;

  /**
   * The queue item.
   *
   * @var string
   */
  protected $queueItem;

  /**
   * The QueueUIManager.
   *
   * @var \Drupal\queue_ui\QueueUIManager
   */
  private $queueUIManager;

  /**
   * ConfirmItemDeleteForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   */
  public function __construct(MessengerInterface $messenger, QueueUIManager $queueUIManager) {
    $this->messenger = $messenger;
    $this->queueUIManager = $queueUIManager;
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
      $container->get('plugin.manager.queue_ui')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete queue item %queueItem?', ['%queueItem' => $this->queueItem]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone and will force the deletion of the item even if it is currently being processed.');
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
    return 'queue_ui_confirm_item_delete_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form where the settings form is being included in.
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
    $queue_ui->deleteItem($this->queueItem);

    $this->messenger->addMessage("Deleted queue item " . $this->queueItem);
    $form_state->setRedirectUrl(Url::fromRoute('queue_ui.inspect', ['queueName' => $this->queueName]));
  }

}

<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfirmClearForm declaration.
 *
 * @package Drupal\queue_ui\Form
 * @phpstan-consistent-constructor
 */
class ConfirmClearForm extends ConfirmFormBase {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  private $tempStoreFactory;

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Queue worker manager service.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * Queue factory instance.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * ConfirmClearForm constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   Queue worker manager service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory instance.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, Messenger $messenger, RendererInterface $renderer, QueueWorkerManagerInterface $queueWorkerManager, QueueFactory $queueFactory) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->messenger = $messenger;
    $this->renderer = $renderer;
    $this->queueWorkerManager = $queueWorkerManager;
    $this->queueFactory = $queueFactory;
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
      $container->get('tempstore.private'),
      $container->get('messenger'),
      $container->get('renderer'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_confirm_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the queues to be deleted from the temp store.
    $queues = $this->tempStoreFactory
      ->get('queue_ui_clear_queues')
      ->get($this->currentUser()->id());
    if (!$queues) {
      return $this->redirect('queue_ui.overview_form');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $queues = $this->tempStoreFactory
      ->get('queue_ui_clear_queues')
      ->get($this->currentUser()->id());

    return $this->formatPlural(
      count($queues),
      'Are you sure you want to clear the queue?',
      'Are you sure you want to clear @count queues?'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $queues = $this->tempStoreFactory
      ->get('queue_ui_clear_queues')
      ->get($this->currentUser()->id());
    array_walk($queues, [$this, 'prepareQueueName']);
    $text = [
      '#type' => 'container',
      'list' => [
        '#theme' => 'item_list',
        '#title' => $this->t('The list of queue to proceed:'),
        '#type' => 'ul',
        '#items' => $queues,
      ],
      'description' => [
        '#plain_text' => $this->t('All items in each queue will be deleted, regardless of if leases exist. This operation cannot be undone.'),
      ],
    ];
    return $this->renderer->render($text);
  }

  /**
   * Modifies the list of queue with human-readable strings.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $name
   *   Name of queue from the list.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function prepareQueueName(&$name): void {
    $definition = $this->queueWorkerManager->getDefinition($name);
    $name = $this->t('@title [%name]', [
      '@title' => $definition['title'],
      '%name' => $name,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('queue_ui.overview_form');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queues = $this->tempStoreFactory
      ->get('queue_ui_clear_queues')
      ->get($this->currentUser()->id());

    foreach ($queues as $name) {
      $queue = $this->queueFactory->get($name);
      $queue->deleteQueue();
    }

    $this->messenger->addMessage($this->formatPlural(count($queues), 'Queue deleted', '@count queues cleared'));
    $form_state->setRedirect('queue_ui.overview_form');
  }

}

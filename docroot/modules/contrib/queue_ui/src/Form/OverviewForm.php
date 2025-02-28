<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\queue_ui\QueueUIBatchInterface;
use Drupal\queue_ui\QueueUIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QueueUIOverviewForm declaration.
 *
 * @package Drupal\queue_ui\Form
 * @phpstan-consistent-constructor
 */
class OverviewForm extends FormBase {

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Drupal state storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Drupal module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConnection;

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  private $queueWorkerManager;

  /**
   * The QueueUIManager.
   *
   * @var \Drupal\queue_ui\QueueUIManager
   */
  private $queueUIManager;

  /**
   * The QueueUIBatchInterface.
   *
   * @var \Drupal\queue_ui\QueueUIBatchInterface
   */
  protected $queueUiBatch;

  /**
   * OverviewForm constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   The queue plugin manager.
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\queue_ui\QueueUIBatchInterface $queue_ui_batch
   *   The batch service.
   */
  public function __construct(QueueFactory $queue_factory, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user, StateInterface $state, ModuleHandlerInterface $module_handler, QueueWorkerManagerInterface $queueWorkerManager, QueueUIManager $queueUIManager, MessengerInterface $messenger, QueueUIBatchInterface $queue_ui_batch) {
    $this->queueFactory = $queue_factory;
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->state = $state;
    $this->moduleHandler = $module_handler;
    $this->dbConnection = Database::getConnection('default');

    $this->queueWorkerManager = $queueWorkerManager;
    $this->queueUIManager = $queueUIManager;
    $this->messenger = $messenger;
    $this->queueUiBatch = $queue_ui_batch;
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
      $container->get('queue'),
      $container->get('tempstore.private'),
      $container->get('current_user'),
      $container->get('state'),
      $container->get('module_handler'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('plugin.manager.queue_ui'),
      $container->get('messenger'),
      $container->get('queue_ui.batch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['top'] = [
      'operation' => [
        '#type' => 'select',
        '#title' => $this->t('Action'),
        '#options' => [
          'submitBatch' => $this->t('Batch process'),
          'submitRelease' => $this->t('Remove leases'),
          'submitClear' => $this->t('Clear'),
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['form-actions'],
        ],
        'apply' => [
          '#type' => 'submit',
          '#tableselect' => TRUE,
          '#submit' => ['::submitBulkForm'],
          '#value' => $this->t('Apply to selected items'),
        ],
      ],
    ];

    $form['queues'] = [
      '#type' => 'table',
      '#tableselect' => TRUE,
      '#header' => [
        'title' => $this->t('Title'),
        'name' => $this->t('Machine name'),
        'items' => $this->t('Number of items'),
        'class' => $this->t('Class'),
        'cron' => $this->t('Cron time limit (seconds)'),
        'operations' => $this->t('Operations'),
      ],
      '#empty' => $this->t('No queues defined'),
    ];

    $queue_order_installed = $this->moduleHandler->moduleExists('queue_order');
    if ($queue_order_installed) {
      // Add the dragable options for the form.
      $form['queues']['#tabledrag'] = [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'queue-order-weight',
        ],
      ];

      // Add the weight to the table header.
      $form['queues']['#header']['weight'] = $this->t('Weight');
      // Add this element so the weight values from the table rows get
      // submitted to form_state.
      $form['weight'] = [
        '#type' => 'table',
      ];
    }
    /**
     * @var array $queues
    */
    $queues = $this->queueWorkerManager->getDefinitions();
    foreach ($queues as $name => $queue_definition) {
      $queue = $this->queueFactory->get($name);

      $operations = [];
      // If queue inspection is enabled for this implementation.
      if ($this->queueUIManager->fromQueueName($name)) {
        $operations['inspect'] = [
          'title' => $this->t('Inspect'),
          'url' => Url::fromRoute('queue_ui.inspect', ['queueName' => $name]),
        ];
      }

      $row = [
        'title' => [
          '#markup' => (string) $queue_definition['title'],
        ],
        'name' => [
          '#markup' => $name,
        ],
        'items' => [
          '#markup' => $queue->numberOfItems(),
        ],
        'class' => [
          '#markup' => $this->queueUIManager->queueClassName($queue),
        ],
        'cron' => [
          '#type' => 'number',
          '#title' => $this->t('Cron Time'),
          '#title_display' => 'hidden',
          '#placeholder' => $this->t('Cron disabled'),
          '#value' => ($queue_definition['cron']['time'] ?? ''),
          '#parents' => [],
          '#name' => 'cron[' . $name . ']',
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $operations,
        ],
      ];

      // Enable sort if queue_order is enabled.
      if ($queue_order_installed) {
        $weight = $queue_definition['weight'] ?? 10;
        $row['#attributes'] = ['class' => ['draggable']];
        $row['#weight'] = $weight;
        $row['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $name]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#name' => 'weight[' . $name . ']',
          // Classify the weight element for #tabledrag.
          '#attributes' => ['class' => ['queue-order-weight']],
          '#parents' => ['weight', $name],
        ];
      }

      $form['queues'][$name] = $row;
    }

    // Add this element so the cron values from the table rows get submitted to
    // form_state.
    $form['cron'] = [
      '#type' => 'table',
    ];

    $form['botton'] = [
      'actions' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['form-actions'],
        ],
        'apply' => [
          '#type' => 'submit',
          '#tableselect' => TRUE,
          '#submit' => ['::submitBulkForm'],
          '#value' => $this->t('Apply to selected items'),
        ],
        'save' => [
          '#type' => 'submit',
          '#value' => $this->t('Save changes'),
        ],
      ],
    ];

    return $form;
  }

  /**
   * We need this method, but each button has its own submit handler.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    foreach ($form_state->getValue('cron') as $name => $time) {
      $this->state->set('queue_ui_cron_' . $name, $time);
    }

    // Only save the weight if the queue_order module is available.
    if ($this->moduleHandler->moduleExists('queue_order')) {
      $order_config = $this->configFactory()->getEditable('queue_order.settings');
      // Save the weight of the defined workers.
      foreach ($form_state->getValue('weight') as $name => $weight) {
        $order_config->set('order.' . $name, (int) $weight);
      }
      $order_config->save();
    }

    // Clear the cached plugin definition so that changes come into effect.
    $this->queueWorkerManager->clearCachedDefinitions();
  }

  /**
   * Process bulk submission.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitBulkForm(array &$form, FormStateInterface $form_state) {
    if (in_array($form_state->getValue('operation'), [
      'submitBatch',
      'submitRelease',
      'submitClear',
    ])) {
      $selected_queues = array_filter($form_state->getValue('queues'));

      if (!empty($selected_queues)) {
        $this->{$form_state->getValue('operation')}($form_state, $selected_queues);
      }
    }
  }

  /**
   * Process queue(s) with batch.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $queues
   *   An array of queue information.
   */
  public function submitBatch(FormStateInterface $form_state, array $queues) {
    $this->queueUiBatch->batch($queues);
  }

  /**
   * Option to remove lease timestamps.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $queues
   *   An array of queue information.
   */
  public function submitRelease(FormStateInterface $form_state, array $queues) {
    foreach ($queues as $queueName) {
      /** @var \Drupal\queue_ui\QueueUIInterface $queue_ui */
      if ($queue_ui = $this->queueUIManager->fromQueueName($queueName)) {
        $num_updated = $queue_ui->releaseItems($queueName);

        $this->messenger->addMessage($this->t('@count lease reset in queue @name', [
          '@count' => $num_updated,
          '@name' => $queueName,
        ]));
      }
    }
  }

  /**
   * Option to delete queue.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $queues
   *   An array of queue information.
   */
  public function submitClear(FormStateInterface $form_state, array $queues) {
    $this->tempStoreFactory->get('queue_ui_clear_queues')
      ->set($this->currentUser->id(), $queues);

    $form_state->setRedirect('queue_ui.confirm_clear_form');
  }

}

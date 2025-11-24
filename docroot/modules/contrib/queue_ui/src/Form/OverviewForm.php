<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Database\Connection;
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
use Drupal\queue_ui\QueueInfoTrait;
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

  use QueueInfoTrait;

  /**
   * The database connection.
   */
  protected Connection $dbConnection;

  /**
   * OverviewForm constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The tempstore factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   The queue plugin manager.
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\queue_ui\QueueUIBatchInterface $queueUiBatch
   *   The batch service.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected AccountInterface $currentUser,
    protected StateInterface $state,
    protected ModuleHandlerInterface $moduleHandler,
    private QueueWorkerManagerInterface $queueWorkerManager,
    private QueueUIManager $queueUIManager,
    MessengerInterface $messenger,
    protected QueueUIBatchInterface $queueUiBatch,
  ) {
    $this->dbConnection = Database::getConnection('default');
    $this->messenger = $messenger;
  }

  /**
   * Retrieves the title for a given derivative worker ID.
   *
   * @param string|null $derivative_worker_id
   *   The derivative worker ID to get the title for. Can be NULL.
   *
   * @return string
   *   The title of the derivative worker, or 'Queue manager' if no match is
   *   found.
   */
  public function title(?string $derivative_worker_id = NULL): string {
    if ($derivative_worker_id) {
      $queues = $this->queueWorkerManager->getDefinitions();
      foreach ($queues as $name => $queue) {
        if (str_starts_with($name, $derivative_worker_id . ':')) {
          return $this->getQueueTitle($queue);
        }
      }
    }
    return $this->t('Queue manager');
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
  public function buildForm(array $form, FormStateInterface $form_state, ?string $derivative_worker_id = NULL) {
    $queue_ui_features = $this->state->get('queue_ui.features', []);
    $derivatives_grouping = !empty($queue_ui_features['derivatives']);

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
        'cron' => $this->t('Cron time limit'),
        'operations' => $this->t('Operations'),
      ],
      '#empty' => $this->t('No queues defined'),
    ];

    $queue_order_installed = $this->moduleHandler->moduleExists('queue_order');
    if ($queue_order_installed) {
      // Add the draggable options for the form.
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
    $processed_derivatives = [];
    foreach ($queues as $name => $queue_definition) {
      // Early exit for derivative overview form.
      if ($derivative_worker_id && !str_starts_with($name, $derivative_worker_id . ':')) {
        continue;
      }

      // Row initialization.
      $operations = [];
      $queue = $this->queueFactory->get($name);

      // Prepare derivatives handling.
      $is_derivative = $queue_definition['deriver'] ?? NULL;
      // Only filled in main overview form.
      $detected_derivative_worker_id = NULL;
      // If it is a main overview form.
      if ($derivatives_grouping && $is_derivative && !$derivative_worker_id) {
        $detected_derivative_worker_id = explode(':', $name)[0];
        $name = $detected_derivative_worker_id;
        if (isset($processed_derivatives[$detected_derivative_worker_id])) {
          $processed_derivatives[$detected_derivative_worker_id]['count'] += $queue->numberOfItems();
          continue;
        }
        $processed_derivatives[$detected_derivative_worker_id] = ['count' => $queue->numberOfItems()];

        $operations['overview'] = [
          'title' => $this->t('Overview'),
          'url' => Url::fromRoute(
            'queue_ui.overview_form.derivative',
            ['derivative_worker_id' => $detected_derivative_worker_id]
          ),
        ];
      }

      // If queue inspection is enabled for this implementation.
      if (!($derivatives_grouping && $detected_derivative_worker_id) && $this->queueUIManager->fromQueueName($name)) {
        $operations['inspect'] = [
          'title' => $this->t('Inspect'),
          'url' => Url::fromRoute('queue_ui.inspect', ['queueName' => $name]),
        ];
      }
      if ($detected_derivative_worker_id) {
        // In context of enabled derivatives feature of main overview form.
        $title = $this->getQueueTitle($queue_definition);
      }
      elseif ($derivatives_grouping && isset($queue_definition['admin_label'])) {
        $title = $this->getQueueLabel($queue_definition) ?: $this->getQueueTitle($queue_definition);
      }
      else {
        $title = $this->getQueueFullTitle($queue_definition);
      }

      $cron_time = $queue_definition['cron']['time'] ?? '';
      $cron_display = $cron_time !== '' ? $this->formatPlural(
        $cron_time,
        '@seconds second',
        '@seconds seconds',
        ['@seconds' => $cron_time]
      ) : $this->t('Cron disabled');

      $operations['cron'] = [
        'title' => $this->t('Cron settings'),
        'url' => Url::fromRoute('queue_ui.cron_form', ['queue' => $name]),
      ];

      $row = [
        'title' => [
          '#markup' => $title,
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
          '#markup' => $detected_derivative_worker_id ? '' : $cron_display,
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
      $row['#attributes']['data-queue-name'] = $name;
      $form['queues'][$name] = $row;
    }

    // Restore Derivative count of items in the queues.
    foreach ($processed_derivatives as $id => $processed_info) {
      $form['queues'][$id]['items']['#markup'] = $processed_info['count'];
    }

    $form['bottom'] = [
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

    $form['features_section'] = [
      '#type' => 'details',
      '#title' => t('Queue Manager Features'),
      '#open' => FALSE,
      '#description' => t('Select the features you want to enable'),
    ];

    $form['features_section']['features'] = [
      '#type' => 'checkboxes',
      '#options' => [
        'derivatives' => t('Derivatives Grouping'),
      ],
      '#default_value' => $queue_ui_features,
    ];

    $form['features_section']['features']['derivatives']['#description'] = $this->t(
      'Once enabled derivative queues will be grouped by it main worker ID. Then all derivative queues can be viewed in the separate table.'
    );

    if ($queue_order_installed) {
      $form['features_section']['features']['#options']['queue_order'] = $this->t(
        'Queue Order'
      );
      $form['features_section']['features']['queue_order']['#disabled'] = TRUE;
      $form['features_section']['features']['queue_order']['#attributes'] = [
        'checked' => 'checked',
      ];
      $form['features_section']['features']['queue_order']['#description'] = $this->t(
        'Queue Order is installed. To disable feature module Queue Order needs to be uninstalled.'
      );
    }

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

    $selected_features = $form_state->getValue('features', []);
    // Only save the weight if the queue_order module is available.
    if ($this->moduleHandler->moduleExists('queue_order')) {
      $order_config = $this->configFactory()->getEditable('queue_order.settings');
      // Save the weight of the defined workers.
      foreach ($form_state->getValue('weight') as $name => $weight) {
        if (!$this->queueWorkerManager->hasDefinition($name)) {
          foreach ($this->queueWorkerManager->getDefinitions() as $queueId => $definition) {
            if (str_starts_with($queueId, $name . ':')) {
              $order_config->set('order.' . $queueId, (int) $weight);
            }
          }
        }
        else {
          $order_config->set('order.' . $name, (int) $weight);
        }
      }
      $order_config->save();
      // Clear the cached plugin definition so that changes come into effect.
      $this->queueWorkerManager->clearCachedDefinitions();
      unset($selected_features['queue_order']);
    }
    $this->state->set('queue_ui.features', $selected_features);
  }

  /**
   * Process bulk submission.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitBulkForm(array &$form, FormStateInterface $form_state): void {
    if (in_array($form_state->getValue('operation'), [
      'submitBatch',
      'submitRelease',
      'submitClear',
    ])) {
      $selected_queues = array_filter($form_state->getValue('queues'));
      if (!empty($this->state->get('queue_ui.features', [])['derivatives'])) {
        $definitions = $this->queueWorkerManager->getDefinitions();
        $derivatives = array_diff($selected_queues, array_keys($definitions));
        if (!empty($derivatives)) {
          foreach ($derivatives as $derivative) {
            foreach ($definitions as $key => $definition) {
              if (str_starts_with($key, $derivative . ':')) {
                $selected_queues[$key] = $key;
              }
            }
            unset($selected_queues[$derivative]);
          }
        }
      }
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
  public function submitBatch(FormStateInterface $form_state, array $queues): void {
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
  public function submitRelease(FormStateInterface $form_state, array $queues): void {
    $result = [];
    foreach ($queues as $queueName) {
      /** @var \Drupal\queue_ui\QueueUIInterface $queue_ui */
      if ($queue_ui = $this->queueUIManager->fromQueueName($queueName)) {
        $num_updated = $queue_ui->releaseItems($queueName);
        $result[] = $this->t('@count lease reset in queue @name', [
          '@count' => $num_updated,
          '@name' => $queueName,
        ]);
      }
    }
    $this->messenger->addStatus(implode("<br/>", $result));
  }

  /**
   * Option to delete queue.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $queues
   *   An array of queue information.
   */
  public function submitClear(FormStateInterface $form_state, array $queues): void {
    $this->tempStoreFactory->get('queue_ui_clear_queues')
      ->set($this->currentUser->id(), $queues);

    $form_state->setRedirect('queue_ui.confirm_clear_form');
  }

}

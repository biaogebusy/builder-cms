<?php

namespace Drupal\queue_ui\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\queue_ui\QueueUIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class QueueUIInspectForm declaration.
 *
 * @package Drupal\queue_ui\Form
 * @phpstan-consistent-constructor
 */
class ItemDetailForm extends FormBase {

  /**
   * Logger.
   */
  protected LoggerChannelInterface $logger;

  /**
   * InspectForm constructor.
   *
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The ModuleHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The Logger object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger instance.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   The queue worker manager.
   */
  public function __construct(
    private QueueUIManager $queueUIManager,
    private RendererInterface $renderer,
    private ModuleHandlerInterface $moduleHandler,
    LoggerChannelFactoryInterface $loggerFactory,
    MessengerInterface $messenger,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {
    $this->logger = $loggerFactory->get('queue_ui');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.queue_ui'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('plugin.manager.queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_item_detail_form';
  }

  /**
   * Returns the page title.
   *
   * @param string $queueName
   *   The name of the queue.
   * @param string $queueItem
   *   The queue item ID.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable markup object representing the page title.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  final public function pageTitleCallback(string $queueName, string $queueItem): TranslatableMarkup {
    $queue_worker = $this->queueWorkerManager->getDefinition($queueName);
    return $this->t('Queue %name Item %id Details', [
      '%name' => $queue_worker['title'],
      '%id' => $queueItem,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $queueName = FALSE, $queueItem = FALSE) {
    try {
      $queueDefinition = $this->queueWorkerManager->getDefinition($queueName);
    }
    catch (PluginNotFoundException $e) {
      $this->messenger->addWarning($this->t('No queue found with name %name', [
        '%name' => $queueName,
      ]));
      $this->logger->notice('No queue found with name %name', [
        '@id' => $queueItem,
        '@name' => $queueName,
      ]);
      throw new NotFoundHttpException();
    }

    try {
      /** @var \Drupal\queue_ui\QueueUIInterface $queueUi */
      $queueUi = $this->queueUIManager->fromQueueName($queueName);
      $queueItemLoaded = $queueUi->loadItem($queueItem);
      if (empty($queueItemLoaded)) {
        $this->handleQueueItemNotFound($queueName, $queueItem);
      }
    }
    catch (\Exception $e) {
      $this->handleQueueItemNotFound($queueName, $queueItem);
    }
    $form['#title'] = $this->pageTitleCallback($queueName, $queueItem);
    $form['table'] = [
      '#type' => 'table',
      '#rows' => [
        'id' => [
          'data' => [
            'header' => [
              'data' => $this->t('Item ID'),
              'data-queue-ui-view-item-id-name' => '',
            ],
            'data' => [
              'data' => $queueItemLoaded->item_id,
              'data-queue-ui-view-item-id-value' => '',
            ],
          ],
        ],
        'queueTitle' => [
          'data' => [
            'header' => [
              'data' => $this->t('Queue title'),
              'data-queue-ui-view-queue-title-name' => '',
            ],
            'data' => [
              'data' => $queueDefinition['title'],
              'data-queue-ui-view-queue-title-value' => '',
            ],
          ],
        ],
        'queueName' => [
          'data' => [
            'header' => [
              'data' => $this->t('Queue name'),
              'data-queue-ui-view-queue-name-name' => '',
            ],
            'data' => [
              'data' => $queueItemLoaded->name,
              'data-queue-ui-view-queue-name-value' => '',
            ],
          ],
        ],
        'expire' => [
          'data' => [
            'header' => [
              'data' => $this->t('Expire'),
              'data-queue-ui-view-expire-name' => '',
            ],
            'data' => [
              'data' => ($queueItemLoaded->expire ? date(DATE_RSS, $queueItemLoaded->expire) : $queueItemLoaded->expire),
              'data-queue-ui-view-expire-value' => '',
            ],
          ],
        ],
        'created' => [
          'data' => [
            'header' => [
              'data' => $this->t('Created'),
              'data-queue-ui-view-created-name' => '',
            ],
            'data' => [
              'data' => date(DATE_RSS, $queueItemLoaded->created),
              'data-queue-ui-view-created-value' => '',
            ],
          ],
        ],
        'data' => [
          'data' => [
            'header' => [
              'data' => $this->t('Data'),
              'style' => 'vertical-align:top',
              'data-queue-ui-view-data-name' => '',
            ],
            'data' => [
              'data' => [
                '#type' => 'html_tag',
                '#tag' => 'pre' ,
                '#value' => print_r(unserialize($queueItemLoaded->data, ['allowed_classes' => FALSE]), TRUE),
              ],
              'data-queue-ui-view-data-value' => '',
            ],
          ],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Handles the case when a queue item is not found.
   *
   * @param string $queueName
   *   The name of the queue.
   * @param string $queueItem
   *   The ID of the queue item.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  private function handleQueueItemNotFound(string $queueName = '', string $queueItem = ''): void {
    $this->messenger->addWarning($this->t('No queue item found with ID %id under queue %name', [
      '%id' => $queueItem,
      '%name' => $queueName,
    ]));
    $this->logger->notice('No queue item found with ID %id under queue %name', [
      '%id' => $queueItem,
      '%name' => $queueName,
    ]);
    throw new NotFoundHttpException();
  }

}

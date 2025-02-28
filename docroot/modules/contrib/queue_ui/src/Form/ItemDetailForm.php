<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
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
   * The QueueUIManager.
   *
   * @var \Drupal\queue_ui\QueueUIManager
   */
  private $queueUIManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * The ModuleHandler object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Messenger instance.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * InspectForm constructor.
   *
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The ModuleHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $loggerFactory
   *   The Logger object.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   Messenger instance.
   */
  public function __construct(QueueUIManager $queueUIManager, RendererInterface $renderer, ModuleHandlerInterface $moduleHandler, LoggerChannelFactoryInterface $loggerFactory = NULL, MessengerInterface $messenger = NULL) {
    $this->queueUIManager = $queueUIManager;
    $this->renderer = $renderer;
    $this->moduleHandler = $moduleHandler;
    if ($loggerFactory === NULL) {
      $loggerFactory = \Drupal::service('logger.factory');
    }
    $this->logger = $loggerFactory->get('queue_ui');
    if ($messenger === NULL) {
      $messenger = \Drupal::service('messenger');
    }
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
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_item_detail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $queueName = FALSE, $queueItem = FALSE) {
    if ($queue_ui = $this->queueUIManager->fromQueueName($queueName)) {
      try {
        $queueItemLoaded = $queue_ui->loadItem($queueItem);
      }
      catch (\Exception $e) {
        $this->messenger->addWarning($this->t('No queue item found with ID @id under queue @name', [
          '@id' => $queueItem,
          '@name' => $queueName,
        ]));
        $this->logger->notice("No queue item found with ID @id under queue @name", [
          '@id' => $queueItem,
          '@name' => $queueName,
        ]);
        throw new NotFoundHttpException();
      }

      $data = [
        '#type' => 'html_tag',
        '#tag' => 'pre' ,
        '#value' => print_r(unserialize($queueItemLoaded->data, ['allowed_classes' => FALSE]), TRUE),
      ];
      $data = $this->renderer->renderPlain($data);

      $rows = [
        'id' => [
          'data' => [
            'header' => $this->t('Item ID'),
            'data' => $queueItemLoaded->item_id,
          ],
        ],
        'queueName' => [
          'data' => [
            'header' => $this->t('Queue name'),
            'data' => $queueItemLoaded->name,
          ],
        ],
        'expire' => [
          'data' => [
            'header' => $this->t('Expire'),
            'data' => ($queueItemLoaded->expire ? date(DATE_RSS, $queueItemLoaded->expire) : $queueItemLoaded->expire),
          ],
        ],
        'created' => [
          'data' => [
            'header' => $this->t('Created'),
            'data' => date(DATE_RSS, $queueItemLoaded->created),
          ],
        ],
        'data' => [
          'data' => [
            'header' => [
              'data' => $this->t('Data'),
              'style' => 'vertical-align:top',
            ],
            'data' => $data,
          ],
        ],
      ];

      return [
        'table' => [
          '#type' => 'table',
          '#rows' => $rows,
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}

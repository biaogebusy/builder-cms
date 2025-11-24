<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\queue_ui\QueueUIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InspectForm declaration.
 *
 * @package Drupal\queue_ui\Form
 * @phpstan-consistent-constructor
 */
class InspectForm extends FormBase {

  /**
   * InspectForm constructor.
   *
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   The queue worker manager.
   */
  public function __construct(
    private QueueUIManager $queueUIManager,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {}

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
      $container->get('plugin.manager.queue_ui'),
      $container->get('plugin.manager.queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_inspect_form';
  }

  /**
   * Returns the page title for the given queue name.
   *
   * @param string $queueName
   *   The name of the queue.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable markup object representing the page title.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  final public function pageTitleCallback(string $queueName): TranslatableMarkup {
    $queue_worker = $this->queueWorkerManager->getDefinition($queueName);
    return $this->t('Inspecting %name Queue', [
      '%name' => $queue_worker['title'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $queueName = FALSE) {
    $form['#title'] = $this->pageTitleCallback($queueName);
    if ($queue_ui = $this->queueUIManager->fromQueueName($queueName)) {

      $rows = [];
      foreach ($queue_ui->getItems($queueName) as $item) {
        $operations = [];
        foreach ($queue_ui->getOperations() as $op => $title) {
          $operations[] = [
            'title' => $title,
            'url' => Url::fromRoute('queue_ui.inspect.' . $op, [
              'queueName' => $queueName,
              'queueItem' => $item->item_id,
            ]),
          ];
        }

        $rows[] = [
          'id' => $item->item_id,
          'expires' => ($item->expire ? date(DATE_RSS, $item->expire) : $item->expire),
          'created' => date(DATE_RSS, $item->created),
          'operations' => [
            'data' => [
              '#type' => 'dropbutton',
              '#links' => $operations,
            ],
          ],
        ];
      }

      $form += [
        'table' => [
          '#type' => 'table',
          '#header' => [
            'id' => $this->t('Item ID'),
            'expires' => $this->t('Expires'),
            'created' => $this->t('Created'),
            'operations' => $this->t('Operations'),
          ],
          '#rows' => $rows,
        ],
        'pager' => [
          '#type' => 'pager',
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}

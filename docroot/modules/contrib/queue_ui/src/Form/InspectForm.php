<?php

namespace Drupal\queue_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * The QueueUIManager.
   *
   * @var \Drupal\queue_ui\QueueUIManager
   */
  private $queueUIManager;

  /**
   * InspectForm constructor.
   *
   * @param \Drupal\queue_ui\QueueUIManager $queueUIManager
   *   The QueueUIManager object.
   */
  public function __construct(QueueUIManager $queueUIManager) {
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
      $container->get('plugin.manager.queue_ui')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_ui_inspect_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $queueName = FALSE) {
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

      return [
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

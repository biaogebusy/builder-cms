<?php

namespace Drupal\advancedqueue\Form;

use Drupal\advancedqueue\BackendManager;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue form.
 */
class QueueForm extends EntityForm {

  /**
   * The backend plugin manager.
   *
   * @var \Drupal\advancedqueue\BackendManager
   */
  protected $backendManager;

  /**
   * Constructs a new QueueForm object.
   *
   * @param \Drupal\advancedqueue\BackendManager $backend_manager
   *   The backend plugin manager.
   */
  public function __construct(BackendManager $backend_manager) {
    $this->backendManager = $backend_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.advancedqueue_backend')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->entity;
    $backends = array_column($this->backendManager->getDefinitions(), 'label', 'id');
    asort($backends);

    // Use the first available backend as the default value.
    if (!$queue->getBackendId()) {
      $backend_ids = array_keys($backends);
      $backend_id = reset($backend_ids);
      $queue->setBackendId($backend_id);
    }
    // The form state will have a backend value if #ajax was used.
    $backend_id = $form_state->getValue('backend', $queue->getBackendId());
    // Pass the configuration only if the backend hasn't been changed via #ajax.
    $backend_configuration = $queue->getBackendId() == $backend_id ? $queue->getBackendConfiguration() : [];
    $backend = $this->backendManager->createInstance($backend_id, $backend_configuration);

    $wrapper_id = Html::getUniqueId('queue-form');
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $queue->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $queue->id(),
      '#machine_name' => [
        'exists' => '\Drupal\advancedqueue\Entity\Queue::load',
      ],
      '#disabled' => !$queue->isNew(),
    ];
    $form['backend'] = [
      '#type' => 'radios',
      '#title' => $this->t('Backend'),
      '#options' => $backends,
      '#default_value' => $backend_id,
      '#required' => TRUE,
      '#disabled' => !$queue->isNew(),
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
      '#access' => count($backends) > 1,
    ];
    $form['configuration'] = [
      '#type' => 'container',
      // The backend needs to be initialized with the defaults before the new
      // configuration is processed in validateForm() / submitForm().
      '#default_configuration' => $backend_configuration,
      // NestedArray::setValue() crashes when switching between two plugins
      // that share a configuration element of the same name, but not the
      // same type (e.g. "amount" of type number/commerce_price).
      // Configuration must be keyed by plugin ID in $form_state to prevent
      // that, either on this level, or in a parent form element.
      '#parents' => ['configuration', $backend_id],
    ];
    $form['configuration'] = $backend->buildConfigurationForm($form['configuration'], $form_state);

    $form['processor'] = [
      '#type' => 'radios',
      '#title' => $this->t('Process the queue via:'),
      '#options' => [
        QueueInterface::PROCESSOR_CRON => $this->t('Cron'),
        QueueInterface::PROCESSOR_DAEMON => $this->t('Daemon (Drush / Drupal Console)'),
      ],
      '#default_value' => $queue->getProcessor(),
    ];
    $form['processing_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Processing time'),
      '#description' => $this->t('How long the queue is processed. 0 means "unlimited", and requires processing via daemon.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $queue->getProcessingTime(),
      '#min' => 0,
    ];

    $threshold = $queue->getThreshold();
    $threshold_type = $threshold['type'] ?? 0;
    $threshold_limit = $threshold['limit'] ?? 0;
    $threshold_state = $threshold['state'] ?? 'all';

    // Ajax callbacks need to be in form state.
    $user_input = $form_state->getUserInput();
    if (isset($user_input['threshold']['type'])) {
      $threshold_type = $user_input['threshold']['type'] ?? 0;
      $threshold_limit = $user_input['threshold']['limit'] ?? 0;
      $threshold_state = $user_input['threshold']['state'] ?? 'all';
      $form_state->set(['threshold', 'type'], $threshold_type);
      $form_state->set(['threshold', 'limit'], $threshold_limit);
      $form_state->set(['threshold', 'state'], $threshold_state);
    }

    $threshold_wrapper_id = Html::getUniqueId('threshold-wrapper');

    $form['threshold'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $threshold_wrapper_id . '">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    $form['threshold']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Remove completed items'),
      '#description' => $this->t('Remove completed items based on items count or number of days.'),
      '#default_value' => $threshold_type,
      '#options' => [
        0 => $this->t('Leave all items'),
        QueueInterface::QUEUE_THRESHOLD_ITEMS => $this->t('Number of items'),
        QueueInterface::QUEUE_THRESHOLD_DAYS => $this->t('Number of days'),
      ],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxCallback'],
        'wrapper' => $threshold_wrapper_id,
      ],
    ];

    $form['threshold']['limit'] = [
      '#type' => 'select',
      '#title' => $threshold_type == QueueInterface::QUEUE_THRESHOLD_DAYS ? $this->t('Number of days') : $this->t('Number of items'),
      '#description' => $threshold_type == QueueInterface::QUEUE_THRESHOLD_DAYS ? $this->t('How long to keep completed items in the database.') : $this->t('Number of completed items to keep in the database.'),
      '#field_suffix' => $threshold_type == QueueInterface::QUEUE_THRESHOLD_DAYS ? $this->t('days') : $this->t('items'),
      '#options' => $threshold_type == QueueInterface::QUEUE_THRESHOLD_DAYS ? array_combine(QueueInterface::QUEUE_THRESHOLD_DAYS_LIMITS, QueueInterface::QUEUE_THRESHOLD_DAYS_LIMITS) : array_combine(QueueInterface::QUEUE_THRESHOLD_ITEMS_LIMITS, QueueInterface::QUEUE_THRESHOLD_ITEMS_LIMITS),
      '#default_value' => $threshold_limit,
      '#access' => !empty($threshold_type),
    ];

    $form['threshold']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('Completed jobs state to remove'),
      '#description' => $this->t('State of completed items to remove.'),
      '#default_value' => $threshold_state,
      '#options' => [
        'all' => $this->t('Both failed and success state'),
        Job::STATE_SUCCESS => $this->t('Only success state'),
      ],
      '#access' => !empty($threshold_type),
    ];

    return $form;
  }

  /**
   * Ajax callback for returning threshold element.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    $element_parents = array_slice($form_state->getTriggeringElement()['#array_parents'], 0, 1);
    return NestedArray::getValue($form, $element_parents);
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();
    $backend = $this->backendManager->createInstance($values['backend'], $form['configuration']['#default_configuration']);
    $backend->validateConfigurationForm($form['configuration'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $values = $form_state->getValues();
    /** @var \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface $backend */
    $backend = $this->backendManager->createInstance($values['backend'], $form['configuration']['#default_configuration']);
    $backend->submitConfigurationForm($form['configuration'], $form_state);
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->entity;
    $queue->setBackendConfiguration($backend->getConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addStatus($this->t('Saved the %label queue.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.advancedqueue_queue.collection');
  }

}

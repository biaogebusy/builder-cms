<?php

namespace Drupal\advancedqueue\Plugin\views\field;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\QueueAction\Delete;
use Drupal\advancedqueue\QueueAction\Release;
use Drupal\advancedqueue\QueueAction\Retry;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an advanced queue operations bulk form element.
 *
 * Inspired by \Drupal\views\Plugin\views\field\BulkForm.
 *
 * @ViewsField("advancedqueue_bulk_form")
 */
#[ViewsField("advancedqueue_bulk_form")]
class AdvancedQueueBulkForm extends FieldPluginBase implements CacheableDependencyInterface {
  use UncacheableFieldHandlerTrait;
  use RedirectDestinationTrait;

  public const TEMP_STORE = 'job_operation_multiple_confirm';

  /**
   * The queue ID field alias.
   */
  protected string $queueIdAlias;

  /**
   * The job ID field alias.
   */
  protected string $jobIdAlias;

  /**
   * The job state field alias.
   */
  protected string $jobStateAlias;

  /**
   * The private temp store.
   */
  protected PrivateTempStore $tempStore;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
    PrivateTempStoreFactory $temp_store_factory,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempStore = $temp_store_factory->get(static::TEMP_STORE);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore new.static
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('tempstore.private'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // @todo Consider making the bulk operation form cacheable. See
    //   https://www.drupal.org/node/2503009.
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['action_title'] = ['default' => $this->t('Action')];
    $options['include_exclude'] = [
      'default' => 'exclude',
    ];
    $options['selected_actions'] = [
      'default' => [],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['action_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action title'),
      '#default_value' => $this->options['action_title'],
      '#description' => $this->t('The title shown above the actions dropdown.'),
    ];

    $form['include_exclude'] = [
      '#type' => 'radios',
      '#title' => $this->t('Available actions'),
      '#options' => [
        'exclude' => $this->t('All actions, except selected'),
        'include' => $this->t('Only selected actions'),
      ],
      '#default_value' => $this->options['include_exclude'],
    ];
    $form['selected_actions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Selected actions'),
      '#options' => $this->getBulkOptions(FALSE),
      '#default_value' => $this->options['selected_actions'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::validateOptionsForm($form, $form_state);

    $selected_actions = $form_state->getValue(['options', 'selected_actions']);
    $form_state->setValue(['options', 'selected_actions'], array_values(array_filter($selected_actions)));
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    parent::preRender($values);

    // If the view is using a table style, provide a placeholder for a
    // "select all" checkbox.
    if ($this->view->style_plugin instanceof Table) {
      // Add the tableselect css classes.
      $this->options['element_label_class'] .= 'select-all';
      // Hide the actual label of the field on the table header.
      $this->options['label'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL): string {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * Form constructor for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(&$form, FormStateInterface $form_state): void {
    // Make sure we do not accidentally cache this form.
    // @todo Evaluate this again in https://www.drupal.org/node/2503009.
    $form['#cache']['max-age'] = 0;
    // All queue actions require the administer advancedqueue permission.
    if (!$this->currentUser->hasPermission('administer advancedqueue')) {
      return;
    }

    // Add the tableselect javascript.
    $form['#attached']['library'][] = 'core/drupal.tableselect';

    // Only add the bulk form options and buttons if there are results.
    if (!empty($this->view->result)) {
      // Render checkboxes for all rows.
      $form[$this->options['id']]['#tree'] = TRUE;
      foreach ($this->view->result as $row_index => $row) {
        $form[$this->options['id']][$row_index] = [
          '#type' => 'checkbox',
            // We are not able to determine a main "title" for each row, so we
            // can only output a generic label.
          '#title' => $this->t('Update this item'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
            // Queue IDs cannot contain dots.
          '#return_value' => $this->calculateBulkFormKey($row),
        ];
      }

      // Replace the form submit button label.
      $form['actions']['submit']['#value'] = $this->t('Apply to selected items');

      // Ensure a consistent container for filters/operations in the view
      // header.
      $form['header'] = [
        '#type' => 'container',
        '#weight' => -100,
      ];

      // Build the bulk operations action widget for the header.
      // Allow themes to apply .container-inline on this separate container.
      $form['header'][$this->options['id']] = [
        '#type' => 'container',
      ];
      $form['header'][$this->options['id']]['action'] = [
        '#type' => 'select',
        '#title' => $this->options['action_title'],
        '#options' => $this->getBulkOptions(),
        '#empty_option' => $this->t('- Select -'),
      ];

      // Duplicate the form actions into the action container in the header.
      $form['header'][$this->options['id']]['actions'] = $form['actions'];
    }
    else {
      // Remove the default actions build array.
      unset($form['actions']);
    }
  }

  /**
   * Calculates the bulk form key from the view result row.
   *
   * @param \Drupal\views\ResultRow $row
   *   The views result row.
   *
   * @return string
   *   The bulk form key representing the queue ID, job ID, and job state as one
   *   string.
   */
  protected function calculateBulkFormKey(ResultRow $row): string {
    $key_parts = [
      $row->{$this->queueIdAlias},
      $row->{$this->jobIdAlias},
      $row->{$this->jobStateAlias},
    ];
    // JSON then Base64 encoding ensures the bulk_form_key is safe to use in
    // HTML, and that the key parts can be retrieved.
    $key = json_encode($key_parts);
    return base64_encode($key);
  }

  /**
   * Returns the available actions.
   *
   * @param bool $filtered
   *   (optional) Whether to filter actions to selected actions.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   An associative array of actions, suitable for a select element.
   */
  protected function getBulkOptions($filtered = TRUE): array {
    $options = [];

    // Filter the action list.
    foreach (static::getActions() as $id => $action) {
      if ($filtered) {
        $in_selected = in_array($id, $this->options['selected_actions']);
        // If the field is configured to include only the selected actions,
        // skip actions that were not selected.
        if (($this->options['include_exclude'] == 'include') && !$in_selected) {
          continue;
        }
        // Otherwise, if the field is configured to exclude the selected
        // actions, skip actions that were selected.
        elseif (($this->options['include_exclude'] == 'exclude') && $in_selected) {
          continue;
        }
      }

      $options[$id] = $action->label();
    }

    return $options;
  }

  /**
   * A hardcoded list of supported actions.
   *
   * @return array<'release'|'retry'|'delete', \Drupal\advancedqueue\QueueAction\QueueActionInterface>
   *   An array of supported actions. Keyed by an identifier.
   */
  public static function getActions(): array {
    return [
      'delete' => new Delete(),
      'release' => new Release(),
      'retry' => new Retry(),
    ];
  }

  /**
   * Submit handler for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user tried to access an action without access to it.
   */
  public function viewsFormSubmit(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('step') == 'views_form_views_form') {
      // Filter only selected checkboxes. Use the actual user input rather than
      // the raw form values array, since the site data may change before the
      // bulk form is submitted, which can lead to data loss.
      $user_input = $form_state->getUserInput();
      $selected = array_filter($user_input[$this->options['id']]);

      $jobs = $queues = [];
      $action = static::getActions()[$form_state->getValue('action')];
      foreach ($selected as $bulk_form_key) {
        $key = base64_decode($bulk_form_key);
        [$queue_id, $job_id, $job_state] = json_decode($key);
        if (!isset($queues[$queue_id])) {
          $queues[$queue_id] = Queue::load($queue_id);
        }
        $queue_backend = $queues[$queue_id]->getBackend();

        if (!$action->canExecute($queue_backend, $job_state)) {
          $this->messenger()->addError($this->t('Cannot execute %action on the job ID %job_id.', [
            '%action' => $action->label(),
            '%job_id' => $job_id,
          ]));
          continue;
        }

        $jobs[$queue_id][] = $job_id;
      }

      // If there were jobs selected but the action isn't allowed on any of
      // them, we don't need to do anything further.
      if (empty($jobs)) {
        return;
      }

      $this->tempStore->set($this->currentUser->id() . ':' . $action->id(), $jobs);

      $options = [
        'query' => $this->getDestinationArray(),
      ];
      $form_state->setRedirect('advancedqueue.bulk_action_confirm', ['action' => $action->id()], $options);
    }
  }

  /**
   * Returns the message to be displayed when there are no selected items.
   *
   * @return string
   *   Message displayed when no items are selected.
   */
  protected function emptySelectedMessage(): TranslatableMarkup {
    return $this->t('No items selected.');
  }

  /**
   * Returns the message that is displayed when no action is selected.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Message displayed when no action is selected.
   */
  protected function emptyActionMessage(): TranslatableMarkup {
    return $this->t('No %title option selected.', ['%title' => $this->options['action_title']]);
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(&$form, FormStateInterface $form_state): void {
    $ids = $form_state->getValue($this->options['id']);
    if (empty($ids) || empty(array_filter($ids))) {
      $form_state->setErrorByName('', $this->emptySelectedMessage());
    }

    $action = $form_state->getValue('action');
    if (empty($action)) {
      $form_state->setErrorByName('', $this->emptyActionMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    assert($this->query instanceof Sql);
    // Ensure we have the data we need to process the actions.
    $this->queueIdAlias = $this->query->addField($this->tableAlias, 'queue_id');
    $this->jobIdAlias = $this->query->addField($this->tableAlias, 'job_id');
    $this->jobStateAlias = $this->query->addField($this->tableAlias, 'state');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    return FALSE;
  }

}

<?php

namespace Drupal\webform\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Element\WebformHtmlEditor;

/**
 * Provides a webform deletion confirmation form.
 */
class WebformEntityDeleteMultipleForm extends WebformDeleteMultipleFormBase {

  /**
   * Associative array container total results for selected webforms.
   *
   * @var array
   */
  protected $totalNumberOfResults;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL) {
    $form = parent::buildForm($form, $form_state, $entity_type_id);

    // Set total results.
    /** @var \Drupal\webform\WebformEntityStorageInterface $webform_storage */
    $webform_storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    $webform_ids = array_keys($this->selection);
    $this->totalNumberOfResults = $webform_storage->getTotalNumberOfResults($webform_ids);

    // Determine if submissions can be deleted.
    $total = array_sum($this->totalNumberOfResults);
    $batch_delete_size = $this->config('webform.settings')->get('batch.default_batch_delete_size');
    if ($total > $batch_delete_size) {
      return $this->buildDeleteSubmissionsForm();
    }
    else {
      $form['entities'] = $this->getEntitiesSummary();
      if (empty($total)) {
        unset($form['description']['list']['#items']['delete']);
      }
      return $form;
    }
  }

  /**
   * Build delete submissions form.
   *
   * @return array
   *   A render array containing a delete submissions form.
   */
  protected function buildDeleteSubmissionsForm() {
    $total = array_sum($this->totalNumberOfResults);
    $batch_delete_size = $this->config('webform.settings')->get('batch.default_batch_delete_size');

    $form = [];
    $form['#title'] = $this->t('Please delete submissions from the selected webforms.');
    $form['#theme'] = 'confirm_form';
    $form['#attributes']['class'][] = 'confirmation';
    // Message.
    $t_args = ['@total' => $total, '@batch' => $batch_delete_size];
    $form['message'] = [
      '#type' => 'webform_message',
      '#message_type' => 'warning',
      '#message_message' => $this->t('The selected webforms have a total of @total submissions.', $t_args) . '<br/>' .
      $this->t('You may not delete these webforms until there is less than @batch total submissions.', $t_args),
    ];
    // Entities.
    $form['entities'] = $this->getEntitiesSummary();
    return $form;
  }

  /**
   * Get the entities summary table.
   *
   * @return array
   *   A render array containing entities summary table.
   */
  protected function getEntitiesSummary() {
    // Header.
    $header = [];
    $header['title'] = $this->t('Title');
    $header['description'] = [
      'data' => $this->t('Description'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['category'] = [
      'data' => $this->t('Category'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['owner'] = [
      'data' => $this->t('Author'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['results'] = $this->t('Results');
    $header['operations'] = [
      'data' => $this->t('Operations'),
    ];

    $webform_ids = array_keys($this->selection);
    /** @var \Drupal\webform\WebformEntityStorageInterface $webform_storage */
    $webform_storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    /** @var \Drupal\webform\WebformInterface[] $webform */
    $webforms = $webform_storage->loadMultiple($webform_ids);

    // Rows.
    $rows = [];
    foreach ($webforms as $webform) {
      $total = $this->totalNumberOfResults[$webform->id()];
      $row = [];
      // Title.
      $row['title'] = $webform->toLink();
      // Description.
      $row['description']['data'] = WebformHtmlEditor::checkMarkup($webform->get('description'));
      // Category.
      $row['category'] = $webform->get('category');
      // Status.
      $row['status'] = $webform->isOpen() ? $this->t('Open') : $this->t('Closed');
      // Owners.
      $row['owner'] = ($owner = $webform->getOwner()) ? $owner->toLink() : '';
      // Results.
      $row['results'] = $this->totalNumberOfResults[$webform->id()];
      // Operation.
      $route_name = 'entity.webform.results_clear';
      $route_parameters = ['webform' => $webform->id()];
      $route_options = [
        'query' => ['destination' => Url::fromRoute('<current>')->toString()],
      ];
      if ($total) {
        $row['operation'] = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Delete submissions'),
            '#url' => Url::fromRoute($route_name, $route_parameters, $route_options),
            '#attributes' => [
              'class' => ['button'],
            ],
          ],
        ];
      }
      else {
        $row['operation'] = '';
      }
      $rows[] = $row;
    }
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    // @see \Drupal\webform\WebformEntityDeleteForm::getDescription
    $actions = [];
    $actions['remove'] = $this->t('Remove configuration');
    $actions['delete'] = $this->t('Delete all related submissions');
    $actions['affect'] = $this->formatPlural(count($this->selection), 'Affect any fields or nodes which reference this webform', 'Affect any fields or nodes which reference these webform', [
      '@item' => $this->entityType->getSingularLabel(),
      '@items' => $this->entityType->getPluralLabel(),
    ]);
    return [
      'title' => [
        '#markup' => $this->t('This action will…'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $actions,
      ],
    ];
  }

}

<?php

namespace Drupal\webform;

use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Form\WebformConfigEntityDeleteFormBase;

/**
 * Provides a delete webform form.
 */
class WebformEntityDeleteForm extends WebformConfigEntityDeleteFormBase {

  /**
   * Total submissions.
   *
   * @var int
   */
  protected $total;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $total = $this->getTotalSubmissions();
    $batch_delete_size = $this->config('webform.settings')->get('batch.default_batch_delete_size');
    if ($total > $batch_delete_size) {
      return $this->buildDeleteSubmissionsForm($form, $form_state);
    }
    else {
      return parent::buildForm($form, $form_state);
    }
  }

  /**
   * Get the total number of submissions for the current webform.
   *
   * @return int
   *   The total number of submissions for the current webform.
   */
  protected function getTotalSubmissions() {
    if (!isset($this->total)) {
      /** @var \Drupal\webform\WebformInterface $webform */
      $webform = $this->getEntity();
      /** @var \Drupal\webform\WebformSubmissionStorageInterface $submission_storage */
      $submission_storage = $this->entityTypeManager->getStorage('webform_submission');
      $this->total = $submission_storage->getTotal($webform);
    }
    return $this->total;
  }

  /**
   * Delete submissions form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The delete submissions form.
   */
  protected function buildDeleteSubmissionsForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getEntity();
    $total = $this->getTotalSubmissions();

    $form['#attributes']['class'][] = 'confirmation';
    $form['#theme'] = 'confirm_form';

    $t_args = [
      '%title' => $webform->label(),
      '@total' => $total,
    ];
    // Title.
    $form['#title'] = $this->t('Please delete submissions from the %title webform.', $t_args);
    // Message.
    $form['message'] = [
      '#type' => 'webform_message',
      '#message_type' => 'warning',
      '#message_message' => $this->t('%title webform has @total submissions.', $t_args) . '<br/>' .
      $this->t('You may not delete %title webform until you have removed all of the %title submissions.', $t_args),
    ];
    // Actions.
    $route_name = 'entity.webform.results_clear';
    $route_parameters = ['webform' => $webform->id()];
    $route_options = [
      'query' => ['destination' => Url::fromRoute('<current>')->toString()],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'link',
        '#title' => $this->t('Delete submissions'),
        '#url' => Url::fromRoute($route_name, $route_parameters, $route_options),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'cancel' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest()),
    ];
    return $this->buildDialogConfirmForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    // @see \Drupal\webform\Form\WebformEntityDeleteMultipleForm::getDescription
    $actions = [];
    $actions[] = $this->t('Remove configuration');
    if ($this->getTotalSubmissions()) {
      $actions[] = $this->t('Delete all related submissions');
    }
    $actions[] = $this->t('Affect any fields or nodes which reference this webform');
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

<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ClearReplicationQueueForm.
 *
 * @package Drupal\replication\Form
 */
class ClearReplicationQueueForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clear_replication_queue_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['clear_queue'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Clear replication queue'),
    );
    $form['clear_queue']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear queue'),
    ];
    $form['clear_queue']['description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="description">' . $this->t('All replications will be marked as failed and removed from the cron queue, except those that are in progress.') . '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('workspace.confirm_clear_replication_queue');
  }

}

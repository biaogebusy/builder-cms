<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\ResultRow;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform submission notes field that allows to edit them.
 *
 * @ViewsField("webform_submission_notes_edit")
 */
class WebformSubmissionNotesEditField extends FieldPluginBase {

  use UncacheableFieldHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * Form constructor for the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    // Make sure we do not accidentally cache this form.
    $form['#cache']['max-age'] = 0;
    // The view is empty, abort.
    if (empty($this->view->result)) {
      unset($form['actions']);
      return;
    }

    $form[$this->options['id']]['#tree'] = TRUE;
    foreach ($this->view->result as $row_index => $row) {
      /** @var WebformSubmissionInterface $webform_submission */
      $webform_submission = $this->getEntity($row);

      $form[$this->options['id']][$row_index] = [
        '#type' => 'textarea',
        '#default_value' => $webform_submission->getNotes(),
      ];
    }
  }

  /**
   * Submit handler for the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsFormSubmit(&$form, FormStateInterface $form_state) {
    foreach ($this->view->result as $row_index => $row) {
      /** @var WebformSubmissionInterface $webform_submission */
      $webform_submission = $this->getEntity($row);

      $notes = $form_state->getValue($form[$this->options['id']][$row_index]['#parents']);
      if ($webform_submission->getNotes() != $notes) {
        $webform_submission->setNotes($notes);
        $webform_submission->save();
      }
    }
  }

}

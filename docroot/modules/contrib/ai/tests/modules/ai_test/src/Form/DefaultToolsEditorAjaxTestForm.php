<?php

declare(strict_types=1);

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form exercising AJAX re-attachment of the default tools editor.
 *
 * The 'tools' textarea below carries the [data-default-tools-editor]
 * selector and the 'ai/default_tools_editor' library. Pressing the button
 * rebuilds the wrapper via AJAX, replacing the textarea with a brand new
 * DOM element, so tests can confirm the editor's Drupal.once marker
 * ('ai-default-tools-editor') is applied again to the new element.
 */
class DefaultToolsEditorAjaxTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_test_default_tools_editor_ajax_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $rebuilt = (bool) $form_state->get('rebuilt');
    // Lets the test request a textarea value the editor's YAML parser
    // cannot handle, to exercise its YAML-mode fallback.
    $malformed = (bool) $this->getRequest()->query->get('malformed');

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'default-tools-editor-ajax-wrapper'],
    ];

    // A plain #markup element is not subject to form-input value processing,
    // so unlike the textarea's own #default_value (which the AJAX request's
    // submitted input would otherwise take precedence over), this reliably
    // proves the wrapper was rebuilt with fresh markup rather than left
    // untouched. Visible text (not just the data attribute) lets a
    // screenshot confirm the rebuild too, not only test assertions.
    $form['wrapper']['rebuild_marker'] = [
      '#markup' => '<p data-rebuild-marker="' . ($rebuilt ? 'rebuilt' : 'initial') . '"><strong>'
      . ($rebuilt ? $this->t('Rebuild marker: rebuilt via AJAX') : $this->t('Rebuild marker: initial load'))
      . '</strong></p>',
    ];

    $form['wrapper']['tools'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default tools'),
      '#attributes' => ['data-default-tools-editor' => 'true'],
      '#attached' => ['library' => ['ai/default_tools_editor']],
      '#default_value' => $malformed ? "tool: [unterminated\n" : "tool: initial-tool\n",
    ];

    $form['rebuild'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update via AJAX'),
      '#submit' => ['::rebuildSubmit'],
      '#ajax' => [
        'callback' => '::updateWrapper',
        'wrapper' => 'default-tools-editor-ajax-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * Submit handler that flags the form for a rebuilt textarea value.
   */
  public function rebuildSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('rebuilt', TRUE);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the rebuilt wrapper.
   */
  public function updateWrapper(array &$form, FormStateInterface $form_state): array {
    return $form['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}

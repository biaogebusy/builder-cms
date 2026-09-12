<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\SelectionDisplay;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_browser\Attribute\EntityBrowserSelectionDisplay;
use Drupal\entity_browser\SelectionDisplayBase;

/**
 * Does not show current selection and immediately delivers selected entities.
 */
#[EntityBrowserSelectionDisplay(
  id: 'no_display',
  label: new TranslatableMarkup('No selection display'),
  description: new TranslatableMarkup('Skips the current selection display and immediately delivers the entities selected.'),
  acceptPreselection: FALSE,
  js_commands: FALSE,
)]
class NoDisplay extends SelectionDisplayBase {

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state) {
    $original_form['#attributes']['class'][] = 'entity-browser-form--no-display';
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$form, FormStateInterface $form_state) {
    // Only finish selection if the form was submitted using main submit
    // element. This allows widgets to build multi-step workflows.
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $this->selectionDone($form_state);
    }
  }

}

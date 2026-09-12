<?php

namespace Drupal\ctools\Ajax;

use Drupal\Core\Ajax\OpenModalDialogCommand;

/**
 * The open modal wizard command.
 */
class OpenModalWizardCommand extends OpenModalDialogCommand {

  /**
   * Constructor.
   */
  public function __construct($object, $tempstore_id, array $parameters = [], array $dialog_options = [], $settings = NULL) {
    // Instantiate the wizard class properly.
    $parameters += [
      'tempstore_id' => $tempstore_id,
      'machine_name' => NULL,
      'step' => NULL,
    ];
    // @phpstan-ignore-next-line AJAX commands cannot use dependency injection.
    $form = \Drupal::service('ctools.wizard.factory')->getWizardForm($object, $parameters, TRUE);
    $title = $form['#title'] ?? '';
    $content = $form;

    parent::__construct($title, $content, $dialog_options, $settings);
  }

}

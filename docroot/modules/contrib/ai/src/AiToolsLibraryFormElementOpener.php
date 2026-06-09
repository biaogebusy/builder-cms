<?php

namespace Drupal\ai;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Class AiToolsLibraryFormElementOpener contains method for tools selection.
 */
class AiToolsLibraryFormElementOpener implements AiToolsLibraryOpenerInterface {

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(AiToolsLibraryState $state, array $selected_ids) {
    $response = new AjaxResponse();

    $parameters = $state->getOpenerParameters();

    // Create a comma-separated list of ai tools IDs, insert them in the hidden
    // field of the widget, and trigger the field update via the hidden submit
    // button.
    $widget_id = $parameters['field_widget_id'];
    $ids = implode(',', $selected_ids);

    $response
      ->addCommand(new InvokeCommand(NULL, 'setToolsFieldValue', [
        $ids,
        "[data-ai-tools-library-form-element-value=\"$widget_id\"]",
      ]))
      ->addCommand(new InvokeCommand("[data-ai-tools-library-form-element-update=\"$widget_id\"]", 'trigger', ['mousedown']))
      ->addCommand(new CloseModalDialogCommand());

    return $response;
  }

}

<?php

namespace Drupal\ai;

/**
 * Interface for AiToolsLibraryOpener classes.
 */
interface AiToolsLibraryOpenerInterface {

  /**
   * Generates a response after selecting tools in the ai tools library.
   *
   * @param \Drupal\ai\AiToolsLibraryState $state
   *   The state the tools library was in at the time of selection, allowing the
   *   response to be customized based on that state.
   * @param int[] $selected_ids
   *   The IDs of the selected ai tools items.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to update the page after selecting ai tools.
   */
  public function getSelectionResponse(AiToolsLibraryState $state, array $selected_ids);

}

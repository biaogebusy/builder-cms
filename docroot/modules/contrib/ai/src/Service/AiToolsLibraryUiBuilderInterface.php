<?php

namespace Drupal\ai\Service;

use Drupal\ai\AiToolsLibraryState;

/**
 * Interface for AiToolsLibraryUiBuilder.
 */
interface AiToolsLibraryUiBuilderInterface {

  /**
   * Builds UI for AI tools library.
   *
   * @param \Drupal\ai\AiToolsLibraryState|null $state
   *   The tools library state.
   *
   * @return array
   *   The render array.
   */
  public function buildUi(?AiToolsLibraryState $state = NULL);

}

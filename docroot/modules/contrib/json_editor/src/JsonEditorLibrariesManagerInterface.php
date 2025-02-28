<?php

namespace Drupal\json_editor;

/**
 * Defines an interface for libraries classes.
 */
interface JsonEditorLibrariesManagerInterface {

  /**
   * Get third party libraries status for hook_requirements and drush.
   *
   * @return array
   *   An associative array of third party libraries keyed by library name.
   */
  public function requirements();

  /**
   * Get library information.
   *
   * @param string $name
   *   The name of the library.
   *
   * @return array
   *   An associative array containing an library.
   */
  public function getLibrary($name);

  /**
   * Get libraries.
   *
   * @param bool|null $included
   *   Optionally filter by include (TRUE) or excluded (FALSE)
   *
   * @return array
   *   An associative array of libraries.
   */
  public function getLibraries($included = NULL);
}
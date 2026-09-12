<?php

namespace Drupal\footable;

/**
 * Provides an interface defining FooTable settings or services.
 */
interface FooTableInterface {

  /**
   * Gets the asset library name based on module configuration.
   *
   * @return string
   *   The library name (e.g., 'footable/footable_standalone_minified').
   */
  public function getLibrary();

  /**
   * Gets all defined FooTable breakpoints.
   *
   * @return array
   *   An associative array of breakpoints keyed by machine name.
   */
  public function getBreakpoints();

}

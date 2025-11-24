<?php

namespace Drupal\footable;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a FooTable breakpoint entity.
 */
interface FooTableBreakpointInterface extends ConfigEntityInterface {

  /**
   * Gets the breakpoint.
   *
   * @return string|int
   *   The breakpoint.
   */
  public function getBreakpoint();

  /**
   * Sets the breakpoint.
   *
   * @param string|int $breakpoint
   *   The breakpoint.
   *
   * @return $this
   */
  public function setBreakpoint($breakpoint);

  /**
   * Returns a list of FooTable breakpoints including the default FooTable
   * breakpoint (All).
   *
   * @return static[]
   *   An array of FooTable breakpoint objects indexed by their IDs.
   */
  public static function loadAll();

}

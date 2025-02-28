<?php

namespace Drupal\footable;

/**
 *
 */
interface FooTableInterface {

  /**
   * @return string
   */
  public function getLibrary();

  /**
   * @return array
   */
  public function getBreakpoints();

}

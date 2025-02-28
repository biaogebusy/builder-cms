<?php

namespace Drupal\xinshi_api;

/**
 * Interface EntityJsonInterface
 * @package Drupal\xinshi_api
 */
interface EntityJsonInterface {

  /**
   * Return entity json view mode.
   * @return array
   */
  public function getContent();

}

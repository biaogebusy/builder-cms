<?php

namespace Drupal\commerce;

/**
 * Provides the interface for the commerce cron.
 */
interface CronInterface {

  /**
   * Runs the cron.
   */
  public function run(): void;

}

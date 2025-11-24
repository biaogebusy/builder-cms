<?php

namespace Drupal\eck\Plugin\migrate;

use Drupal\migrate\Plugin\Migration;
use Drupal\migrate_drupal\Plugin\MigrationWithFollowUpInterface;

/**
 * Migration plugin for the Drupal 7 eck types.
 */
class D7EckType extends Migration implements MigrationWithFollowUpInterface {

  /**
   * {@inheritdoc}
   */
  public function generateFollowUpMigrations() {
    $this->migrationPluginManager->clearCachedDefinitions();
    return $this->migrationPluginManager->createInstances(['d7_eck', 'd7_eck_translation']);
  }

}

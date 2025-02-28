<?php

namespace Drupal\Tests\page_manager\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests context changes.
 *
 * @group Update
 * @group page_manager
 */
class UpdateContextName extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/page_manager.2960739.php',
    ];
  }

  /**
   * Testing of current_user.
   */
  public function testUpdateCurrentUserContextName() {
    $this->runUpdates();
    $pageVariant = \Drupal::entityTypeManager()
      ->getStorage('page_variant')
      ->load('test_page-block_display-0');

    $selection_criteria = $pageVariant->get('selection_criteria');
    $this->assertEquals($selection_criteria[0]['context_mapping']['user'], '@user.current_user_context:current_user');

    $variant_settings = $pageVariant->get('variant_settings');
    $this->assertEquals($variant_settings['blocks']['29550d0e-39f1-4fb9-bad6-c390dda5bd00']['context_mapping']['entity'], '@user.current_user_context:current_user');
  }

}

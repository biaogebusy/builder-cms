<?php

namespace Drupal\Tests\panelizer\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the updating of Layout IDs.
 *
 * @group panelizer
 */
class PanelizerLayoutIDUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    if (str_starts_with(\Drupal::VERSION, '10.')) {
      $base_dump = 'drupal-9.4.0.bare.standard.php.gz';
    }
    else {
      $base_dump = 'drupal-9.3.0.bare.standard.php.gz';
    }
    $this->databaseDumpFiles = [
      $this->getDrupalRoot() . '/core/modules/system/tests/fixtures/update/' . $base_dump,
      __DIR__ . '/../../../fixtures/update/panelizer-installed.php.gz',
    ];
  }

  /**
   * Test updates.
   */
  public function testUpdate() {
    $module_handler = $this->container->get('module_handler');
    $this->assertFalse($module_handler->moduleExists('layout_builder'));
    $this->assertFalse($module_handler->moduleExists('core_context'));
    $this->assertFalse($module_handler->moduleExists('layout_library'));

    $this->runUpdates();

    $module_handler = $this->container->get('module_handler');
    $this->assertTrue($module_handler->moduleExists('layout_builder'));
    $this->assertTrue($module_handler->moduleExists('core_context'));
    $this->assertTrue($module_handler->moduleExists('layout_library'));

    $this->drupalLogin($this->rootUser);
    // Defaults are not editable in Panelizer 5.x.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->clickLink('Edit', 1);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('node/1');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('node/2');
    $this->assertSession()->statusCodeEquals(200);
  }

}

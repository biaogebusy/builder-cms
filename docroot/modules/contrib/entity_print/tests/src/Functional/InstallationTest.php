<?php

namespace Drupal\Tests\entity_print\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the modules install and uninstall cleanly.
 *
 * @group entity_print
 */
class InstallationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
  }

  /**
   * Test the installation and uninstallation of the the modules.
   */
  public function testInstallation() {
    $this->assertInstallationStatus(FALSE);
    $this->installModules();
    $this->assertInstallationStatus(TRUE);
    $this->uninstallModules();
    $this->assertInstallationStatus(FALSE);
    $this->installModules();
    $this->assertInstallationStatus(TRUE);
  }

  /**
   * Assert the installation status of the modules.
   *
   * @param bool $installed
   *   If the modules should be installed or not.
   */
  protected function assertInstallationStatus($installed) {
    $this->drupalGet('admin/modules');
    foreach (['entity_print', 'entity_print_views'] as $module) {
      $this->assertSession()->{$installed ? 'checkboxChecked' : 'checkboxNotChecked'}('modules[' . $module . '][enable]');
    }
  }

  /**
   * Uninstall the module using the UI.
   */
  protected function uninstallModules() {
    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm([
      'uninstall[entity_print_views]' => TRUE,
    ], 'Uninstall');
    $this->getSession()->getPage()->pressButton('Uninstall');
    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm([
      'uninstall[entity_print]' => TRUE,
    ], 'Uninstall');
    $this->getSession()->getPage()->pressButton('Uninstall');
  }

  /**
   * Install the modules using the UI.
   */
  protected function installModules() {
    $this->drupalGet('admin/modules');
    $this->submitForm([
      'modules[entity_print][enable]' => TRUE,
      'modules[entity_print_views][enable]' => TRUE,
    ], 'Install');
    // Continue is only required to confirm dependencies being enabled on the
    // first call of this function.
    if ($button = $this->getSession()->getPage()->findButton('Continue')) {
      $button->press();
    }
  }

}

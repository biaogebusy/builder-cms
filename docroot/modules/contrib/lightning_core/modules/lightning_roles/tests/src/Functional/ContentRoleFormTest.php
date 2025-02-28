<?php

namespace Drupal\Tests\lightning_roles\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning_roles
 * @group lightning_core
 * @group lightning
 * @group orca_public
 */
class ContentRoleFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lightning_core',
    'lightning_roles',
  ];

  public function test() {
    $account = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($account);

    $this->drupalGet("/admin/config/system/lightning/roles");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('content_roles[reviewer]')->check();
    $this->assertSession()->buttonExists('Save configuration')->press();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('content_roles[reviewer]')->uncheck();
    $this->assertSession()->buttonExists('Save configuration')->press();
    $this->assertSession()->statusCodeEquals(200);
  }

}

<?php

namespace Drupal\Tests\module_filter\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class for Module Filter tests.
 */
abstract class ModuleFilterBrowserTestBase extends BrowserTestBase {

  /**
   * The standard modules to load for all browser tests.
   *
   * Additional modules can be specified in the tests that need them.
   *
   * @var array
   */
  protected static $modules = ['module_filter'];

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with administration rights.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an administrator user with the required permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer modules',
    ]);
    $this->adminUser->set('name', 'Minnie the Admin')->save();
  }

}

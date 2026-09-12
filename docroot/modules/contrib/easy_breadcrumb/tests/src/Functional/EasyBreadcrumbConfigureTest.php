<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests configuring easy_breadcrumb.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbConfigureTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['easy_breadcrumb'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests admin access and basic configuration saving.
   */
  public function testAdministrationAccessAndConfigurationSave() {
    $assert = $this->assertSession();
    $config_after_install = $this->config('easy_breadcrumb.settings')->get();

    // Tests that a user cannot access the admin section by default.
    $this->drupalGet('admin/config/user-interface/easy-breadcrumb');
    $assert->statusCodeEquals(403);

    // Tests that users with the permission 'administer easy breadcrumb' can
    // access the admin section.
    $this->drupalLogin($this->createUser(['administer easy breadcrumb']));
    $this->drupalGet('admin/config/user-interface/easy-breadcrumb');
    $assert->statusCodeEquals(200);

    // Tests saving the configuration form.
    $this->submitForm([], 'Save configuration');
    $assert->statusCodeEquals(200);
    $assert->pageTextContainsOnce('The configuration options have been saved.');

    // Tests that saving the configuration form without changing any values
    // does not change the configuration.
    $this->assertSame($config_after_install, $this->config('easy_breadcrumb.settings')->get());
  }

}

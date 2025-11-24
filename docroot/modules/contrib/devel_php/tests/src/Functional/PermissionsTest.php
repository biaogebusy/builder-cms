<?php

declare(strict_types=1);

namespace Drupal\Tests\devel_php\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests permissions.
 *
 * @group devel_php
 */
class PermissionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'devel',
    'devel_php',
  ];

  /**
   * Tests user permissions to execute code.
   */
  public function testPermissionToExecuteCode(): void {
    $url = Url::fromRoute('devel_php.execute_php');

    // Anonymous user.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // User without permissions.
    $user = $this->drupalCreateUser();
    if (!($user instanceof UserInterface)) {
      $this->fail('Impossible to create the tests user.');
    }

    $this->drupalLogin($user);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    $user = $this->drupalCreateUser(['execute php code']);
    if (!($user instanceof UserInterface)) {
      $this->fail('Impossible to create the tests user.');
    }

    $this->drupalLogin($user);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', "//form[@id='devel-execute-form']");
  }

}

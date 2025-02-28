<?php

namespace Drupal\Tests\lightning_roles\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * @group lightning_core
 * @group lightning
 * @group orca_public
 */
class ContentOverviewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lightning_roles',
    'node',
  ];

  public function test() {
    $node_type = $this->createContentType()->id();

    $role = Role::load($node_type . '_reviewer');
    $this->assertInstanceOf(Role::class, $role);

    $account = $this->createUser();
    $account->addRole($role->id());
    $account->save();

    $this->drupalLogin($account);
    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
  }

}

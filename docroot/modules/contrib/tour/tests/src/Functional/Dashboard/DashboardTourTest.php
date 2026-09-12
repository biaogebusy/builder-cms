<?php

namespace Drupal\Tests\tour\Functional\Dashboard;

use Drupal\Tests\tour\Functional\TourTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Dashboard tour tips.
 */
#[Group('tour')]
class DashboardTourTest extends TourTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tour_dashboard_test'];

  /**
   * {@inheritdoc}
   */
  protected array $permissions = [
    'access administration pages',
  ];

  /**
   * Tests Dashboard tour tip availability.
   */
  public function testDashboardTourTips(): void {
    $role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $role->grantPermission('view main_dashboard dashboard')->save();
    $role->grantPermission('view extra_dashboard dashboard')->save();
    $role->grantPermission('view third_dashboard dashboard')->save();

    $this->drupalGet('/admin/dashboard');
    $this->assertTourTips();

    $this->drupalGet('/admin/dashboard/extra_dashboard');
    $this->assertTourTips();

    $this->drupalGet('/admin/dashboard/third_dashboard');
    $this->assertTourTips([], TRUE);
  }

}

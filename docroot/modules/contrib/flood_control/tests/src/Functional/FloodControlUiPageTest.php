<?php

namespace Drupal\Tests\flood_control\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that the Flood control UI pages are reachable.
 *
 * @group flood_control
 */
class FloodControlUiPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['flood_control'];

  /**
   * The admin user that can access the admin page.
   *
   * @var string
   */
  private $adminUser;

  /**
   * A simple user without any permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  private $simpleUser;

  /**
   * A simple user with the "administer flood unblock" permission.
   *
   * @var \Drupal\user\Entity\User
   */
  private $settingsUser;

  /**
   * A simple user with the "flood unblock ips" permission.
   *
   * @var \Drupal\user\Entity\User
   */
  private $floodUnblockUser;

  /**
   * Create required user and other objects in order to run tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();

    $this->settingsUser = $this->drupalCreateUser(['administer flood unblock']);
    $this->floodUnblockUser = $this->drupalCreateUser(['flood unblock ips']);
    $this->simpleUser = $this->drupalCreateUser([]);

    // Flood backends need a request object. Create a dummy one and insert it
    // to the container.
    $request = Request::createFromGlobals();
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    // The flood table is opportunistically created during first use. In these
    // tests, that does not happen all the time in Drupal 9 but the table must
    // exist for the Database backend to work.
    $database = $this->container->get('database');
    if (!$database->schema()->tableExists('flood')) {
      $flood = $this->container->get('flood');
      $flood->register('flood_control.test', 9675309, 'jenny-');
      $flood->clear('flood_control.test', 'jenny-');
    }
  }

  /**
   * Test flood control with admin user.
   */
  public function testAccessAdmin() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/admin/people/flood-unblock');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty flood list.
    $this->assertSession()
      ->pageTextContains('There are no failed logins at this time.');

    // Check if link to control settings appears, as the user has the
    // permission to see it:
    $this->assertSession()
      ->pageTextContains('You can configure the login attempt limits and time windows on the Flood Control settings page');
  }

  /**
   * Flood control for admin pages with "administer flood unblock" permission.
   */
  public function testAccessAdministerFloodUnblockPermission() {
    $this->drupalLogin($this->settingsUser);

    // Check access on both pages with the "administer flood unblock"
    // permission:
    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('/admin/people/flood-unblock');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test flood control admin pages with "flood unblock ips" permission.
   */
  public function testAccessFloodUnblockIpsPermission() {
    $this->drupalLogin($this->floodUnblockUser);

    // Check access on both pages with the "flood unblock ips"
    // permission:
    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/people/flood-unblock');
    $this->assertSession()->statusCodeEquals(200);

    // Check if link to control settings does not appear, as the user does not
    // have the permission to see it:
    $this->assertSession()
      ->pageTextNotContains('You can configure the login attempt limits and time windows on the Flood Control settings page');
  }

  /**
   * Test flood control with admin pages with "no" permission.
   */
  public function testAccessNoPermission() {
    $this->drupalLogin($this->simpleUser);
    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/people/flood-unblock');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test flood control admin pages as anonymous.
   */
  public function testAccessAnonymous() {

    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/people/flood-unblock');
    $this->assertSession()->statusCodeEquals(403);
  }

}

<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\profile\ProfileTestTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Views;

/**
 * Tests the profile module view handlers.
 *
 * @group profile
 */
class ProfileViewHandlersTest extends ViewTestBase {
  use ProfileTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'profile_test',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'profile_handlers_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The first test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $firstUser;

  /**
   * The second test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $secondUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['profile_test']): void {
    parent::setUp($import_test_views, $modules);

    // Create a test profile type.
    $type = $this->createProfileType('test_profile', 'Test profile');

    // Create users with profiles.
    $permissions = [
      'access content',
      'view any test_profile profile',
    ];
    $this->firstUser = $this->drupalCreateUser($permissions);
    $this->secondUser = $this->drupalCreateUser($permissions);
    $this->createProfile($type, $this->firstUser);
    $this->createProfile($type, $this->secondUser);

    // Make sure view updated to have test_profile type.
    $view = Views::getView('profile_handlers_test');
    $arguments = $view->getDisplay()->getOption('arguments');
    $arguments['profile_id']['default_argument_options']['type'] = 'test_profile';
    $view->getDisplay()->overrideOption('arguments', $arguments);
    $view->save();
  }

  /**
   * Tests the profile view default argument handler.
   */
  public function testProfileOwnerArgumentDefault() {
    // Make sure the first user can see only their profile.
    $this->drupalLogin($this->firstUser);
    $this->drupalGet('profile-handlers-test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("{$this->firstUser->getAccountName()} is owner of the profile");
    $this->assertSession()->pageTextNotContains("{$this->secondUser->getAccountName()} is owner of the profile");

    // Make sure the second user can see only their profile.
    $this->drupalLogin($this->secondUser);
    $this->drupalGet('profile-handlers-test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("{$this->secondUser->getAccountName()} is owner of the profile");
    $this->assertSession()->pageTextNotContains("{$this->firstUser->getAccountName()} is owner of the profile");
  }

}

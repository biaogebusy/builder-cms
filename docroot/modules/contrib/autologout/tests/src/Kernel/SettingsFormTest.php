<?php

namespace Drupal\Tests\autologout\Kernel;

use Drupal\autologout\Form\AutologoutSettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the settings form.
 *
 * @description Ensures that the settings form functions as expected.
 *
 * @group Autologout
 */
class SettingsFormTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'system',
    'autologout',
  ];

  /**
   * User with admin rights.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $privilegedUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserData
   */
  public $userData;

  /**
   * The autologout settings form.
   *
   * @var \Drupal\autologout\Form\AutologoutSettingsForm
   */
  protected $settingsForm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('system', ['sequences']);
    $this->installConfig('autologout');

    $this->configFactory = $this->container->get('config.factory');
    $this->userData = $this->container->get('user.data');
    $this->privilegedUser = $this->createUser(['change own logout threshold']);
  }

  /**
   * Tests the behaviour of the settings upon submission.
   */
  public function testSettingsForm() {
    $form_builder = $this->container->get('form_builder');
    $settings = $this->configFactory->getEditable('autologout.settings');
    $roles = user_roles();
    $settings->set('max_timeout', 1000)->save();

    // Test that it is possible to set a value above the max_timeout threshold.
    $form_state = (new FormState())
      ->setValues([
        'timeout' => 1500,
        'max_timeout' => 2000,
        'padding' => 60,
        'role_logout' => TRUE,
        'redirect_url' => '/user/login',
      ]);

    foreach ($roles as $key => $role) {
      $form_state->setValue(['table', $key, 'enabled'], TRUE);
      $form_state->setValue(['table', $key, 'timeout'], 1200);
      $form_state->setValue(['table', $key, 'url'], '/user/login');
    }

    $form_builder->submitForm(AutologoutSettingsForm::class, $form_state);
    $this->assertCount(0, $form_state->getErrors());

    // Test that out of range values are picked up.
    $form_state->setValues([
      'timeout' => 2500,
      'max_timeout' => 2000,
      'padding' => 60,
      'role_logout' => TRUE,
      'redirect_url' => '/user/login',
    ]);

    foreach ($roles as $key => $role) {
      $form_state->setValue(['table', $key, 'enabled'], TRUE);
      $form_state->setValue(['table', $key, 'timeout'], 1200);
      $form_state->setValue(['table', $key, 'url'], '/user/login');
    }

    $form_builder->submitForm(AutologoutSettingsForm::class, $form_state);
    $form_errors = $form_state->getErrors();
    $this->assertCount(1, $form_errors);
    $this->assertEquals(
      'The timeout must be an integer greater than or equal to 60 and less then or equal to <em class="placeholder">2000</em>.',
      $form_errors['timeout']);

    // Test that it's impossible to set max_timeout to greater than 2147483.
    $form_state->setValues([
      'timeout' => 2500,
      'max_timeout' => 2147484,
      'padding' => 60,
      'role_logout' => FALSE,
      'redirect_url' => '/user/login',
    ]);

    foreach ($roles as $key => $role) {
      $form_state->setValue(['table', $key, 'enabled'], TRUE);
      $form_state->setValue(['table', $key, 'timeout'], 2300);
      $form_state->setValue(['table', $key, 'url'], '/user/login');
    }

    $form_builder->submitForm(AutologoutSettingsForm::class, $form_state);
    $form_errors = $form_state->getErrors();
    $this->assertCount(1, $form_errors);
    $this->assertEquals(
      'The max timeout must be an integer lower than or equal to <em class="placeholder">2147483</em>.',
      $form_errors['max_timeout']);

    // Test that out of range values are picked up.
    $form_state->setValues([
      'timeout' => 1500,
      'max_timeout' => 2000,
      'padding' => 60,
      'role_logout' => TRUE,
      'redirect_url' => '/user/login',
    ]);

    foreach ($roles as $key => $role) {
      $form_state->setValue(['table', $key, 'enabled'], TRUE);
      $form_state->setValue(['table', $key, 'timeout'], 2500);
      $form_state->setValue(['table', $key, 'url'], '/user/login');
    }

    $form_builder->submitForm(AutologoutSettingsForm::class, $form_state);
    $form_errors = $form_state->getErrors();
    $this->assertCount(1, $form_errors);
    $this->assertEquals(
      t('%role role timeout must be an integer greater than 60, less then <em class="placeholder">2000</em> or 0 to disable autologout for that role.',
        ['%role' => key($roles)]
      ),
      $form_errors['table][' . key($roles) . '][timeout']);

    // Test that role timeouts are not validated for disabled roles.
    $form_state->setValues([
      'timeout' => 1500,
      'max_timeout' => 2000,
      'padding' => 60,
      'role_logout' => TRUE,
      'redirect_url' => '/user/login',
    ]);

    foreach ($roles as $key => $role) {
      $form_state->setValue(['table', $key, 'enabled'], FALSE);
      $form_state->setValue(['table', $key, 'timeout'], 1200);
      $form_state->setValue(['table', $key, 'url'], '/user/login');
    }

    $form_builder->submitForm(AutologoutSettingsForm::class, $form_state);
    $this->assertCount(0, $form_state->getErrors());

    // Test clearing of users individual timeout when this becomes disabled.
    $uid = $this->privilegedUser->id();
    $this->userData->set('autologout', $uid, 'timeout', 1600);
    $form_state->setValues([
      'no_individual_logout_threshold' => TRUE,
    ]);

    $form_builder->submitForm(AutologoutSettingsForm::class, $form_state);
    $this->assertAutotimeout(
      $uid,
      1500,
      'User timeout is cleared when setting no_individual_logout_threshold is activated.');
  }

  /**
   * Tests the precedence of the timeouts.
   *
   * This tests the following function:
   *  _autologout_get_user_timeout();
   */
  public function testTimeoutPrecedence() {
    $settings = $this->configFactory->getEditable('autologout.settings');
    $user_settings = $this->container->get('user.data');
    $uid = $this->privilegedUser->id();
    $role_settings = $this->configFactory
      ->getEditable('autologout.role.' . key(user_roles()));

    // Default used if no role is specified.
    $settings->set('timeout', 100)
      ->set('role_logout', FALSE)
      ->save();
    $role_settings->set('enabled', FALSE)
      ->set('timeout', 200)
      ->save();
    $this->assertAutotimeout(
      $uid,
      100,
      'User timeout uses default if no other option is set.'
    );

    // Default used if role is selected but no user role is selected.
    $settings->set('role_logout', TRUE)->save();
    $role_settings->set('enabled', FALSE)
      ->set('timeout', 200)
      ->save();
    $this->assertAutotimeout(
      $uid,
      100,
      'User timeout uses default if role timeouts are used but not one of the current user.'
    );

    // Role timeout is used if user role is selected.
    $settings->set('role_logout', TRUE)->save();
    $role_settings->set('enabled', TRUE)
      ->set('timeout', 200)
      ->save();
    $this->assertAutotimeout($uid, 200, 'User timeout uses role value.');

    // Role timeout is used if user role is selected.
    $settings->set('role_logout', TRUE)->save();
    $role_settings->set('enabled', TRUE)
      ->set('timeout', 0)
      ->save();
    $this->assertAutotimeout(
      $uid,
      0,
      'User timeout uses role value of 0 if set for one of the user roles.'
    );

    // Role timeout used if personal timeout is an empty string.
    $settings->set('role_logout', TRUE)->save();
    $role_settings->set('enabled', TRUE)
      ->set('timeout', 200)
      ->save();
    $user_settings->set('autologout', $uid, 'timeout', '');
    $user_settings->set('autologout', $uid, 'enabled', FALSE);
    $this->assertAutotimeout(
      $uid,
      200,
      'User timeout uses role value if personal value is an empty string.'
    );

    // Default timeout used if personal timeout is an empty string.
    $settings->set('role_logout', TRUE)->save();
    $role_settings->set('enabled', FALSE)
      ->set('timeout', 200)
      ->save();
    $user_settings->set('autologout', $uid, 'timeout', '');
    $user_settings->set('autologout', $uid, 'enabled', FALSE);
    $this->assertAutotimeout(
      $uid,
      100,
      'User timeout uses default value if personal value is an empty string and no role timeout is specified.'
    );

    // Personal timeout used if set.
    $settings->set('role_logout', TRUE)->save();
    $role_settings->set('enabled', FALSE)
      ->set('timeout', 200)
      ->save();
    $user_settings->set('autologout', $uid, 'timeout', 300);
    $user_settings->set('autologout', $uid, 'enabled', TRUE);
    $this->assertAutotimeout($uid, 300, 'User timeout uses personal timeout.');
  }

  /**
   * Asserts the timeout for a particular user.
   *
   * @param int $uid
   *   User uid to assert the timeout for.
   * @param int $expected_timeout
   *   The expected timeout.
   * @param string $message
   *   The test message.
   */
  protected function assertAutotimeout($uid, $expected_timeout, $message = '') {
    self::assertEquals(
      $this->container->get('autologout.manager')->getUserTimeout($uid),
      $expected_timeout,
      $message
    );
  }

}

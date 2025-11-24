<?php

namespace Drupal\Tests\autologout\FunctionalJavascript;

use Drupal\Core\Config\Config;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\Entity\User;

/**
 * Tests that user is redirected to its own profile.
 *
 * @group Autologout
 */
class UserDestinationLogoutTest extends WebDriverTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'autologout',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $moduleConfig;

  /**
   * User to logout.
   *
   * @var bool|\Drupal\user\Entity\User|false
   */
  protected $privilegedUser;

  /**
   * User to login.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->baseUrl = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

    $this->privilegedUser = $this->drupalCreateUser();
    $this->testUser = $this->drupalCreateUser();

    $this->moduleConfig = $this->container->get('config.factory')->getEditable('autologout.settings');

    // For testing purposes set the timeout to 5 seconds.
    $this->moduleConfig->set('timeout', 5)->set('padding', 2)->save();

    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Tests that redirection is correct when destination is set.
   *
   * If destination is set for user/login test that user is redirected to its
   * own profile when logged in again.
   */
  public function testDestinationUserLogout(): void {
    // Check that first user is logged in.
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));
    $this->drupalGet('user/' . $this->privilegedUser->id());
    // Used later because of the way that the url is built.
    $user_uri = Url::fromRoute('entity.user.canonical', ['user' => $this->privilegedUser->id()])->toString();
    // Wait for timeout.
    $this->getSession()->wait(10000);
    // Check that destination is set after logout and privilegedUser user
    // is logged out.
    $this->assertSession()->addressEquals($this->getUrl());
    $this->assertStringContainsString('/user/login?destination=' . $user_uri, $this->getSession()->getCurrentUrl());
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));
    // Given we have asserted the user is logged out, reset session. Otherwise,
    // UiHelperTrait::drupalLogin() will fail because
    // UiHelperTrait::$loggedInUser is still set and assumed to be still logged
    // in. It will attempt to logout by visiting the logout confirm page
    // which will fail.
    $this->drupalResetSession();

    // Login testUser and check that user was redirected to its own profile.
    $this->drupalLogin($this->testUser);
    $this->assertSession()->addressEquals($this->baseUrl . '/user/' . $this->testUser->id());
  }

}

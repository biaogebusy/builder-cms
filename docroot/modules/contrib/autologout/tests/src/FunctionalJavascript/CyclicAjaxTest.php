<?php

namespace Drupal\Tests\autologout\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test that checks if cyclic ajax request do not prevent logout.
 *
 * @group Autologout
 */
class CyclicAjaxTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'autologout',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory|object|null
   */
  protected $configFactory;

  /**
   * User to logout.
   *
   * @var bool|\Drupal\user\Entity\User|false
   */
  protected $privilegedUser;

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'administer autologout',
      'change own logout threshold',
      'view the administration theme',
    ]);

    $this->baseUrl = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

    $this->configFactory = \Drupal::service('config.factory');

    // For the purposes of the test, set the timeout periods to 10 seconds.
    $this->configFactory->getEditable('autologout.settings')
      ->set('timeout', 10)
      ->set('padding', 5)
      ->save();
  }

  /**
   * Test not logging out when ajax cyclic request are bombarding the page.
   */
  public function testCyclicRequest() {
    // Login.
    $this->drupalLogin($this->privilegedUser);
    // Run JS.
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));
    $this->getSession()->executeScript("
      function sendRequest() {
        $.ajax({
            url: \"$this->baseUrl\",
            type: 'GET',
            success:
                function (res) {
                    console.log('Request was sent!');
                },
            complete: function () {
                setTimeout(sendRequest, 5000); // The interval set to 5 seconds
            }
        });
      sendRequest();
      }"
    );

    sleep(13);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));
    sleep(5);
    // Logged out.
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));

  }

}

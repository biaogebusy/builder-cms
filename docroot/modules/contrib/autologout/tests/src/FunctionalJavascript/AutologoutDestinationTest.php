<?php

namespace Drupal\Tests\autologout\FunctionalJavascript;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test that checks if the destination parameter is set correctly.
 *
 * @group Autologout
 */
class AutologoutDestinationTest extends WebDriverTestBase {

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
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected ConfigFactory $configFactory;

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

    $this->configFactory = \Drupal::service('config.factory');
  }

  /**
   * Tests that the destination parameter is set to true.
   */
  public function testDestinationTrue() {
    $this->configFactory->getEditable('autologout.settings')
      ->set('timeout', 2)
      ->set('padding', 5)
      ->set('include_destination', TRUE)
      ->save();

    $this->drupalLogin($this->privilegedUser);

    $user_uri = Url::fromRoute('entity.user.canonical', ['user' => $this->privilegedUser->id()])->toString();
    $this->drupalGet('/user/' . $this->privilegedUser->id());

    $session = $this->assertSession();
    $session->waitForElement('css', 'div[aria-describedby=autologout-confirm]', 2500);

    $this->getSession()->wait(5000);

    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('destination=' . $user_uri, $current_url);
  }

  /**
   * Tests that the destination parameter is set to false.
   */
  public function testDestinationFalse() {
    $this->configFactory->getEditable('autologout.settings')
      ->set('timeout', 2)
      ->set('padding', 5)
      ->set('include_destination', FALSE)
      ->save();

    $this->drupalLogin($this->privilegedUser);

    $user_uri = Url::fromRoute('entity.user.canonical', ['user' => $this->privilegedUser->id()])->toString();
    $this->drupalGet('/user/' . $this->privilegedUser->id());

    $session = $this->assertSession();
    $session->waitForElement('css', 'div[aria-describedby=autologout-confirm]', 2500);

    $this->getSession()->wait(5000);

    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringNotContainsString('destination=' . $user_uri, $current_url);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\tourauto\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the tourauto JavaScript functionality.
 */
#[Group('tourauto')]
class TourautoJavascriptTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'tour',
    'tourauto',
    'user',
    'tourauto_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser([
      'access tour',
      'administer users',
    ]);
  }

  /**
   * Tests that tourauto JavaScript is loaded and initialized.
   */
  public function testTourautoJavaScriptLoaded(): void {
    $this->drupalLogin($this->user);

    // Visit a page with tours.
    $this->drupalGet('tourauto-test');

    // Check that tourauto JavaScript is loaded.
    $this->assertSession()->responseContains('tourauto.js');

    // Check that tourauto JavaScript is loaded.
    $this->assertSession()->responseContains('tourauto.js');
  }

  /**
   * Tests that tourauto settings are available in drupalSettings.
   */
  public function testTourautoSettingsInDrupalSettings(): void {
    $this->drupalLogin($this->user);

    // Visit a page with tours.
    $this->drupalGet('tourauto-test');

    // Check that tourauto settings are available in drupalSettings.
    $this->assertSession()->responseContains('tourauto_open');
  }

  /**
   * Tests that tourauto debug information is available for admin users.
   */
  public function testTourautoDebugInformation(): void {
    $admin_user = $this->drupalCreateUser([
      'access tour',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    // Visit a page with tours.
    $this->drupalGet('tourauto-test');

    // Check that debug information is available.
    $this->assertSession()->responseContains('tourauto_debug');
  }

  /**
   * Tests that tourauto does not interfere with normal tour functionality.
   */
  public function testTourautoDoesNotInterfereWithNormalTour(): void {
    $this->drupalLogin($this->user);

    // Visit a page with tours and tour parameter.
    $this->drupalGet('tourauto-test', ['query' => ['tour' => 1]]);

    // Check that tour is still functional.
    $this->assertSession()->pageTextContains('Tourauto Test Tip 1');
  }

  /**
   * Tests that tourauto respects user preferences.
   */
  public function testTourautoRespectsUserPreferences(): void {
    $this->drupalLogin($this->user);

    // Disable tourauto for the user.
    $this->drupalGet('user/' . $this->user->id() . '/edit');
    $this->getSession()->getPage()->uncheckField('tourauto_enable');
    $this->getSession()->getPage()->pressButton('Save');

    // Visit a page with tours.
    $this->drupalGet('tourauto-test');

    // Check that tourauto is not active.
    $this->assertSession()->responseNotContains('tourauto_open');
  }

  /**
   * Tests that tourauto works with tour module's JavaScript.
   */
  public function testTourautoWorksWithTourModule(): void {
    $this->drupalLogin($this->user);

    // Visit a page with tours.
    $this->drupalGet('tourauto-test');

    // Check that both tour and tourauto JavaScript are loaded.
    $this->assertSession()->responseContains('tour.js');
    $this->assertSession()->responseContains('tourauto.js');
  }

}

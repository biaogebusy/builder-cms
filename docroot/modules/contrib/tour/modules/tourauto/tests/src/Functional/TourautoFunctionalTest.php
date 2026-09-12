<?php

declare(strict_types=1);

namespace Drupal\Tests\tourauto\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the tourauto module functionality.
 */
#[Group('tourauto')]
class TourautoFunctionalTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['tour', 'tourauto', 'user'];

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
   * Tests that the tourauto form elements are present on user edit form.
   */
  public function testUserFormElements(): void {
    $this->drupalLogin($this->user);

    // Visit the user edit form.
    $this->drupalGet('user/' . $this->user->id() . '/edit');

    // Check that the tourauto form elements are present.
    $this->assertSession()->fieldExists('tourauto_enable');
    $this->assertSession()->fieldExists('tourauto_clear');

    // Check that the fieldset is present.
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-tourauto"]');
  }

  /**
   * Tests saving tourauto preferences.
   */
  public function testSaveTourautoPreferences(): void {
    $this->drupalLogin($this->user);

    // Visit the user edit form.
    $this->drupalGet('user/' . $this->user->id() . '/edit');

    // Uncheck the tourauto enable checkbox.
    $this->getSession()->getPage()->uncheckField('tourauto_enable');

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Save');

    // Check that the form was submitted successfully.
    $this->assertSession()->pageTextContains('The changes have been saved.');

    // Verify the preference was saved by checking the form again.
    $this->drupalGet('user/' . $this->user->id() . '/edit');
    $this->assertSession()->checkboxNotChecked('tourauto_enable');
  }

  /**
   * Tests clearing tour state.
   */
  public function testClearTourState(): void {
    $this->drupalLogin($this->user);

    // Visit the user edit form.
    $this->drupalGet('user/' . $this->user->id() . '/edit');

    // Check the clear state checkbox.
    $this->getSession()->getPage()->checkField('tourauto_clear');

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Save');

    // Check that the form was submitted successfully.
    $this->assertSession()->pageTextContains('The changes have been saved.');
  }

  /**
   * Tests that tourauto settings are not available for anonymous users.
   */
  public function testAnonymousUserNoSettings(): void {
    // Visit the user edit form as anonymous user.
    $this->drupalGet('user/1/edit');

    // Should be redirected to login.
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Tests that tourauto JavaScript is loaded when enabled.
   */
  public function testTourautoJavaScriptLoaded(): void {
    $this->drupalLogin($this->user);

    // Visit a page with tours (admin page).
    $this->drupalGet('admin');

    // Check that tourauto JavaScript is loaded.
    $this->assertSession()->responseContains('tourauto.js');
  }

  /**
   * Tests that tourauto settings are available in user edit form.
   */
  public function testUserEditFormSettings(): void {
    $this->drupalLogin($this->user);

    // Visit the user edit form.
    $this->drupalGet('user/' . $this->user->id() . '/edit');

    // Check that the tourauto section is present.
    $this->assertSession()->pageTextContains('Tours');
  }

}

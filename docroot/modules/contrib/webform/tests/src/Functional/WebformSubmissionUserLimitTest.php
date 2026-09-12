<?php

namespace Drupal\Tests\webform\Functional;

/**
 * Tests per-user submission limit caching behavior.
 *
 * @group webform
 */
class WebformSubmissionUserLimitTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'path',
    'user',
    'webform',
  ];

  /**
   * Test webform submission user limit with authenticated user.
   */
  public function testSubmissionUserLimitAuthenticatedUser(): void {

    // Create a webform with one checkbox field.
    $this->drupalLogin($this->createUser(['administer webform']));
    $this->drupalGet('/admin/structure/webform/add');
    $this->submitForm([
      'id' => 'test_webform',
      'title' => 'Test Webform',
    ], 'Save');
    $this->drupalGet('/admin/structure/webform/manage/test_webform');
    $this->submitForm([
      'elements' => "checkbox:\n  '#type': checkbox\n  '#title': Accept terms",
    ], 'Save');

    // Limit to 1 submission per user.
    $this->drupalGet('/admin/structure/webform/manage/test_webform/settings/submissions');
    $this->submitForm([
      'limit_user' => '1',
    ], 'Save');

    // Disable anonymous access.
    $this->drupalGet('/admin/structure/webform/manage/test_webform/access');
    $this->submitForm([
      'access[create][roles][anonymous]' => FALSE,
    ], 'Save');

    $this->drupalLogout();

    // Create two users.
    $userA = $this->createUser(['access content']);
    $userB = $this->createUser(['access content']);

    // User A logs in and submits the form.
    $this->drupalLogin($userA);
    $this->drupalGet('/form/test-webform');
    $this->submitForm(['checkbox' => TRUE], 'Submit');

    // Revisit the form; should say blocked.
    $this->drupalGet('/form/test-webform');
    $this->assertSession()->pageTextContains('No more submissions are permitted.');
    $this->drupalLogout();

    // User B logs in; they should be able to submit the form.
    $this->drupalLogin($userB);
    $this->drupalGet('/form/test-webform');
    $this->submitForm(['checkbox' => TRUE], 'Submit');

    // Revisit the form; should say blocked.
    $this->drupalGet('/form/test-webform');
    $this->assertSession()->pageTextContains('No more submissions are permitted.');

    $this->drupalLogout();
  }

  /**
   * Test webform submission user limit with anonymous user.
   */
  public function testSubmissionUserLimitAnonymousUser(): void {
    // Create a webform with one checkbox field.
    $this->drupalLogin($this->createUser(['administer webform']));
    $this->drupalGet('/admin/structure/webform/add');
    $this->submitForm([
      'id' => 'test_webform',
      'title' => 'Test Webform',
    ], 'Save');
    $this->drupalGet('/admin/structure/webform/manage/test_webform');
    $this->submitForm([
      'elements' => "checkbox:\n  '#type': checkbox\n  '#title': Accept terms",
    ], 'Save');

    // Limit to 1 submission.
    $this->drupalGet('/admin/structure/webform/manage/test_webform/settings/submissions');
    $this->submitForm([
      'limit_total' => '1',
    ], 'Save');

    $this->drupalLogout();

    // Anonymous user submits the form.
    $this->drupalGet('/form/test-webform');
    $this->submitForm(['checkbox' => TRUE], 'Submit');

    // Revisit the form; should say blocked.
    $this->drupalGet('/form/test-webform');
    $this->assertSession()->pageTextContains('No more submissions are permitted.');
  }

}

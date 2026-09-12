<?php

namespace Drupal\Tests\webform\Functional\Form;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests for webform submission delete.
 *
 * @group webform
 */
class WebformSubmissionDeleteTest extends WebformBrowserTestBase {

  /**
   * Tests webform submissions delete multiple.
   */
  public function testWebformSubmissionsDeleteMultiple(): void {
    $assert_session = $this->assertSession();
    $own_account = $this->drupalCreateUser([
      'access webform overview',
      'administer webform overview',
      'create webform',
      'edit own webform',
      'delete own webform',
      'administer webform',
    ]);

    /* ********************************************************************** */

    // Login as user who can access own webform.
    $this->drupalLogin($own_account);

    // Create own webform.
    $this->drupalGet('/admin/structure/webform/add');
    $edit = ['id' => 'test_own', 'title' => 'test_own'];
    $this->submitForm($edit, 'Save');
    $this->createSubmissions('test_own', 50);

    // Create own webform.
    $this->drupalGet('/admin/structure/webform/add');
    $edit = ['id' => 'test_own_2', 'title' => 'test_own_2'];
    $this->submitForm($edit, 'Save');
    $this->createSubmissions('test_own_2', 5);

    $edit = [
      'action' => 'webform_delete_action',
      'items[test_own]' => TRUE,
      'items[test_own_2]' => TRUE,
    ];
    $this->drupalGet('/admin/structure/webform');
    $this->submitForm($edit, 'Apply to selected items', 'webform-bulk-form');
    $assert_session->pageTextContains('Delete these webforms?');
    $assert_session->pageTextContains('Are you sure you want to delete these webforms?');

    // Change batch delete size.
    $this->config('webform.settings')->set('batch.default_batch_delete_size', 10)->save();

    $this->drupalGet('/admin/structure/webform');
    $this->submitForm($edit, 'Apply to selected items', 'webform-bulk-form');

    $assert_session->pageTextContains('Please delete submissions from the selected webforms.');
    $assert_session->pageTextContains('The selected webforms have a total of 55 submissions.');
    $assert_session->pageTextContains('You may not delete these webforms until there is less than 10 total submissions.');

    $this->clickLink('Delete submissions');
    $assert_session->pageTextContains('Clear all test_own submissions?');
  }

  /**
   * Tests webform submissions delete.
   */
  public function testWebformSubmissionsDelete(): void {
    $assert_session = $this->assertSession();
    $own_account = $this->drupalCreateUser([
      'access webform overview',
      'create webform',
      'edit own webform',
      'delete own webform',
    ]);

    /* ********************************************************************** */

    // Login as user who can access own webform.
    $this->drupalLogin($own_account);

    // Create own webform.
    $this->drupalGet('/admin/structure/webform/add');
    $edit = ['id' => 'test_own', 'title' => 'test_own'];
    $this->submitForm($edit, 'Save');
    $this->createSubmissions('test_own', 50);

    // Default batch delete is 500, si the form should be shown normally.
    $this->drupalGet('/admin/structure/webform/manage/test_own/delete');
    $assert_session->pageTextContains('Delete test_own webform?');
    $assert_session->pageTextContains('Are you sure you want to delete the test_own webform?');

    // Change batch delete size.
    $this->config('webform.settings')->set('batch.default_batch_delete_size', 10)->save();
    // The delete webform form should show the delete submission.
    $this->drupalGet('/admin/structure/webform/manage/test_own/delete');
    $assert_session->pageTextContains('Please delete submissions from the test_own webform');
    $assert_session->pageTextContains('test_own webform has 50 submissions.');
    $assert_session->pageTextContains('You may not delete test_own webform until you have removed all of the test_own submissions.');
    $assert_session->pageTextContains('Delete submissions');
    $assert_session->pageTextContains('Cancel');
    $this->clickLink('Delete submissions');
    $assert_session->pageTextContains('Clear all test_own submissions?');
  }

  /**
   * Create submissions.
   */
  public function createSubmissions($webform_name, $count) {
    for ($i = 1; $i <= $count; $i++) {
      $values = [
        'webform_id' => $webform_name,
        'uid' => 0,
      ];
      WebformSubmission::create($values)->save();
    }
  }

}

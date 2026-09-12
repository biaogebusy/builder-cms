<?php

namespace Drupal\Tests\webform\Functional;

use Drupal\webform\Entity\Webform;

/**
 * Tests for webform submission list builder.
 *
 * @group webform
 */
class WebformSubmissionListBuilderTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'webform', 'webform_test_submissions'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_submissions'];

  /**
   * Tests results.
   */
  public function testResults(): void {
    $assert_session = $this->assertSession();

    $own_submission_user = $this->drupalCreateUser([
      'view own webform submission',
      'edit own webform submission',
      'delete own webform submission',
      'access webform submission user',
    ]);

    $admin_submission_user = $this->drupalCreateUser([
      'administer webform submission',
    ]);

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::load('test_submissions');

    /** @var \Drupal\webform\WebformSubmissionInterface[] $submissions */
    $submissions = array_values(\Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties(['webform_id' => 'test_submissions']));

    /* ********************************************************************** */

    // Login the own submission user.
    $this->drupalLogin($own_submission_user);

    // Make the second submission to be starred (aka sticky).
    $submissions[1]->setSticky(TRUE)->save();

    // Make the third submission to be locked.
    $submissions[2]->setLocked(TRUE)->save();

    $this->drupalLogin($admin_submission_user);

    /* Filter */

    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/results/submissions');

    // Check state options with totals.
    $assert_session->optionExists('State', 'All [4]');
    $assert_session->optionExists('State', 'Starred [1]');
    $assert_session->optionExists('State', 'Unstarred [3]');
    $assert_session->optionExists('State', 'Locked [1]');
    $assert_session->optionExists('State', 'Unlocked [3]');

    // Check results with no filtering.
    $assert_session->linkByHrefExists($submissions[0]->toUrl()->toString());
    $assert_session->linkByHrefExists($submissions[1]->toUrl()->toString());
    $assert_session->linkByHrefExists($submissions[2]->toUrl()->toString());
    $assert_session->responseContains($submissions[0]->getElementData('first_name'));
    $assert_session->responseContains($submissions[1]->getElementData('first_name'));
    $assert_session->responseContains($submissions[2]->getElementData('first_name'));
    $assert_session->buttonNotExists('reset');

    // Check results filtered by uuid.
    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/results/submissions');
    $edit = ['search' => $submissions[0]->get('uuid')->value];
    $this->submitForm($edit, 'Filter');
    $assert_session->addressEquals('admin/structure/webform/manage/' . $webform->id() . '/results/submissions?search=' . $submissions[0]->get('uuid')->value);
    $assert_session->responseContains($submissions[0]->getElementData('first_name'));
    $assert_session->responseNotContains($submissions[1]->getElementData('first_name'));
    $assert_session->responseNotContains($submissions[2]->getElementData('first_name'));

    // Check results filtered by key(word).
    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/results/submissions');
    $edit = ['search' => $submissions[0]->getElementData('first_name')];
    $this->submitForm($edit, 'Filter');
    $assert_session->addressEquals('admin/structure/webform/manage/' . $webform->id() . '/results/submissions?search=' . $submissions[0]->getElementData('first_name'));
    $assert_session->responseContains($submissions[0]->getElementData('first_name'));
    $assert_session->responseNotContains($submissions[1]->getElementData('first_name'));
    $assert_session->responseNotContains($submissions[2]->getElementData('first_name'));
    $assert_session->buttonExists('Reset');

    // Check results filtered by state:starred.
    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/results/submissions');
    $edit = ['state' => 'starred'];
    $this->submitForm($edit, 'Filter');
    $assert_session->addressEquals('admin/structure/webform/manage/' . $webform->id() . '/results/submissions?state=starred');
    $starredOption = $assert_session->optionExists('state', 'Starred [1]');
    $this->assertEquals('starred', $starredOption->getValue());
    $this->assertTrue($starredOption->isSelected());
    $assert_session->responseNotContains($submissions[0]->getElementData('first_name'));
    $assert_session->responseContains($submissions[1]->getElementData('first_name'));
    $assert_session->responseNotContains($submissions[2]->getElementData('first_name'));
    $assert_session->buttonExists('edit-reset');

    // Check results filtered by state:starred.
    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/results/submissions');
    $edit = ['state' => 'locked'];
    $this->submitForm($edit, 'Filter');
    $assert_session->addressEquals('admin/structure/webform/manage/' . $webform->id() . '/results/submissions?state=locked');
    $lockedOption = $assert_session->optionExists('state', 'Locked [1]');
    $this->assertEquals('locked', $lockedOption->getValue());
    $this->assertTrue($lockedOption->isSelected());
    $assert_session->responseNotContains($submissions[0]->getElementData('first_name'));
    $assert_session->responseNotContains($submissions[1]->getElementData('first_name'));
    $assert_session->responseContains($submissions[2]->getElementData('first_name'));
    $assert_session->buttonExists('edit-reset');
  }

}

<?php

namespace Drupal\Tests\diff\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;

/**
 * Tests the diff revisions overview.
 *
 * @group diff
 */
class DiffRevisionTest extends DiffTestBase {

  use AssertBreadcrumbTrait;
  use CoreVersionUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'diff_test',
    'content_translation',
    'field_ui',
  ];

  /**
   * Tests the revision diff overview.
   */
  public function testRevisionDiffOverview(): void {
    $this->drupalPlaceBlock('system_breadcrumb_block');
    // Login as admin with the required permission.
    $this->loginAsAdmin(['delete any article content']);

    // Create an article.
    $title = 'test_title_a';
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => '<p>Revision 1</p>
      <p>first_unique_text</p>
      <p>second_unique_text</p>',
    ];
    // Set to published if content moderation is enabled.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle($title);

    // Create a second revision, with a revision comment.
    $this->drupalGet('node/add/article');
    $edit = [
      'body[0][value]' => '<p>Revision 2</p>
      <p>first_unique_text</p>
      <p>second_unique_text</p>',
      'revision' => TRUE,
      'revision_log[0][value]' => 'Revision 2 comment',
    ];
    // Set to published if content moderation is enabled.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $this->drupalGet('node/' . $node->id());

    // Check the revisions overview.
    $this->clickLink('Revisions');
    $rows = $this->xpath('//tbody/tr');
    // Make sure only two revisions available.
    $this->assertCount(2, $rows);
    // Assert the revision summary.
    $this->assertSession()->pageTextContainsOnce('Revision 2 comment');

    // Compare the revisions in standard mode.
    $this->submitForm([], 'Compare selected revisions');
    $this->clickLink('Split fields');
    // Assert breadcrumbs are properly displayed.
    $trail = [
      '' => 'Home',
      "node/" . $node->id() => $node->label(),
      "node/" . $node->id() . "/revisions" => 'Revisions',
    ];
    $this->assertBreadcrumb(NULL, $trail);
    // Extract the changes.
    $this->assertSession()->pageTextContains('Body');
    $rows = $this->xpath('//tbody/tr');
    $head = $this->xpath('//thead/tr');
    $diff_row = $rows[1]->findAll('xpath', '/td');
    // Assert the revision comment.
    $this->assertSession()->responseContains('diff-revision__item-message">Revision 2 comment');
    // Assert changes made to the body, text 1 changed to 2.
    $this->assertEquals($diff_row[0]->getText(), '1');
    $this->assertEquals($diff_row[1]->getText(), '-');
    $this->assertEquals($diff_row[2]->find('xpath', 'span')->getText(), '1');
    $this->assertEquals(htmlspecialchars_decode(strip_tags($diff_row[2]->getHtml())), '<p>Revision 1</p>');
    $this->assertEquals($diff_row[3]->getText(), '1');
    $this->assertEquals($diff_row[4]->getText(), '+');
    $this->assertEquals($diff_row[5]->find('xpath', 'span')->getText(), '2');
    $this->assertEquals(htmlspecialchars_decode((strip_tags($diff_row[5]->getHtml()))), '<p>Revision 2</p>');

    // Compare the revisions in markdown mode.
    $this->clickLink('Strip tags');
    $rows = $this->xpath('//tbody/tr');
    // Assert breadcrumbs are properly displayed.
    $trail = [
      '' => 'Home',
      "node/" . $node->id() => $node->label(),
      "node/" . $node->id() . "/revisions" => 'Revisions',
    ];
    $this->assertBreadcrumb(NULL, $trail);
    // Extract the changes.
    $diff_row = $rows[1]->findAll('xpath', '/td');
    // Assert changes made to the body, text 1 changed to 2.
    $this->assertEquals($diff_row[0]->getText(), '-');
    $this->assertEquals($diff_row[1]->find('xpath', 'span')->getText(), '1');
    $this->assertEquals(htmlspecialchars_decode(trim(strip_tags($diff_row[1]->getHtml()))), 'Revision 1');
    $this->assertEquals($diff_row[2]->getText(), '+');
    $this->assertEquals($diff_row[3]->find('xpath', 'span')->getText(), '2');
    $this->assertEquals(htmlspecialchars_decode(trim(strip_tags($diff_row[3]->getHtml()))), 'Revision 2');

    // Compare the revisions in single column mode.
    $this->clickLink('Unified fields');
    // Assert breadcrumbs are properly displayed.
    $trail = [
      '' => 'Home',
      "node/" . $node->id() => $node->label(),
      "node/" . $node->id() . "/revisions" => 'Revisions',
    ];
    $this->assertBreadcrumb(NULL, $trail);
    // Extract the changes.
    $rows = $this->xpath('//tbody/tr');
    $diff_row = $rows[1]->findAll('xpath', '/td');
    // Assert changes made to the body, text 1 changed to 2.
    $this->assertEquals($diff_row[0]->getText(), '1');
    $this->assertEquals($diff_row[1]->getText(), '');
    $this->assertEquals($diff_row[2]->getText(), '-');
    $this->assertEquals($diff_row[3]->find('xpath', 'span')->getText(), '1');
    $this->assertEquals(htmlspecialchars_decode(strip_tags($diff_row[3]->getHtml())), '<p>Revision 1</p>');
    $diff_row = $rows[2]->findAll('xpath', '/td');
    $this->assertEquals($diff_row[0]->getText(), '');
    $this->assertEquals($diff_row[1]->getText(), '1');
    $this->assertEquals($diff_row[2]->getText(), '+');
    $this->assertEquals($diff_row[3]->find('xpath', 'span')->getText(), '2');
    $this->assertEquals(htmlspecialchars_decode(strip_tags($diff_row[3]->getHtml())), '<p>Revision 2</p>');
    $this->assertSession()->pageTextContainsOnce('first_unique_text');
    $this->assertSession()->pageTextContainsOnce('second_unique_text');
    $diff_row = $rows[3]->findAll('xpath', '/td');
    $this->assertEquals($diff_row[0]->getText(), '2');
    $this->assertEquals($diff_row[1]->getText(), '2');
    $diff_row = $rows[4]->findAll('xpath', '/td');
    $this->assertEquals($diff_row[0]->getText(), '3');
    $this->assertEquals($diff_row[1]->getText(), '3');

    $this->clickLink('Strip tags');
    // Extract the changes.
    $rows = $this->xpath('//tbody/tr');
    $diff_row = $rows[1]->findAll('xpath', '/td');

    // Assert changes made to the body, with strip_tags filter and make sure
    // there are no line numbers.
    $this->assertEquals($diff_row[0]->getText(), '-');
    $this->assertEquals($diff_row[1]->find('xpath', 'span')->getText(), '1');
    $this->assertEquals(htmlspecialchars_decode(trim(strip_tags($diff_row[1]->getHtml()))), 'Revision 1');
    $diff_row = $rows[2]->findAll('xpath', '/td');
    $this->assertEquals($diff_row[0]->getText(), '+');
    $this->assertEquals($diff_row[1]->find('xpath', 'span')->getText(), '2');
    $this->assertEquals(htmlspecialchars_decode(trim(strip_tags($diff_row[1]->getHtml()))), 'Revision 2');

    $this->drupalGet('node/' . $node->id());
    $this->clickLink(t('Revisions'));
    // Revert the revision, confirm.
    $this->clickLink(t('Revert'));
    $this->submitForm([], 'Revert');
    $this->assertSession()->pageTextContains('Article ' . $title . ' has been reverted to the revision from');

    // Make sure three revisions are available.
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(3, $rows);
    // Make sure the reverted comment is there.
    $this->assertSession()->pageTextContains('Copy of the revision from');

    // Delete the first revision (last entry in table).
    $this->assertSession()->elementExists('css', '#revision-overview-form')->clickLink('Delete');

    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('of Article ' . $title . ' has been deleted.');

    // Make sure two revisions are available.
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(2, $rows);

    // Delete one revision so that we are left with only 1 revision.
    $this->assertSession()->elementExists('css', '#revision-overview-form')->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('of Article ' . $title . ' has been deleted.');

    // Make sure we only have 1 revision now.
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(0, $rows);

    // Assert that there are no radio buttons for revision selection.
    $this->assertSession()->elementNotExists('xpath', '//input[@type="radio"]');
    // Assert that there is no submit button.
    $this->assertSession()->elementNotExists('xpath', '//input[@type="submit" and text()="Compare selected revisions"]');

    // Create two new revisions of node.
    $edit = [
      'title[0][value]' => 'new test title',
      'body[0][value]' => '<p>new body</p>',
      'revision_log[0][value]' => 'this revision message will appear twice',
    ];
    // Set to published if content moderation is enabled.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');

    $edit = [
      'title[0][value]' => 'newer test title',
      'body[0][value]' => '<p>newer body</p>',
      'revision_log[0][value]' => 'this revision message will appear twice',
    ];
    // Set to published if content moderation is enabled.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');

    $this->clickLink('Revisions');
    // Assert the revision summary.
    $page_text = $this->getSession()->getPage()->getText();
    $nr_found = substr_count($page_text, 'this revision message will appear twice');
    $this->assertGreaterThan(1, $nr_found, "'this revision message will appear twice' found more than once on the page");
    $this->assertSession()->pageTextContains('Copy of the revision from');
    $edit = [
      'radios_left' => '3',
      'radios_right' => '4',
    ];
    $this->submitForm($edit, 'Compare selected revisions');
    $this->clickLink('Strip tags');
    // Check markdown layout is used when navigating between revisions.
    $assert_session = $this->assertSession();
    $assert_session->elementTextContains('css', 'tr:nth-child(4) td:nth-child(4)', 'new body');
    $this->clickLink('Next change');
    // The filter should be the same as the previous screen.
    $assert_session->elementTextContains('css', 'tr:nth-child(4) td:nth-child(4)', 'newer body');

    // Get the node, create a new revision that is not the current one.
    $node = $this->getNodeByTitle('newer test title');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    if ($node->hasField('moderation_state')) {
      // If testing with content_moderation enabled, set as draft.
      $node->moderation_state = 'draft';
    }
    $node->save();
    $this->drupalGet('node/' . $node->id() . '/revisions');

    // Check that the last revision is not the current one.
    $this->assertSession()->linkExists('Set as current revision');
    $text = $this->xpath('//tbody/tr[2]/td[4]/em');
    $this->assertEquals($text[0]->getText(), 'Current revision');

    // Set the last revision as current.
    $this->clickLink('Set as current revision');
    $this->submitForm([], 'Revert');

    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      // With content moderation, the new revision will not be current.
      // @see https://www.drupal.org/node/2899719
      $text = $this->xpath('//tbody/tr[1]/td[4]/div/div/ul/li/a');
      $this->assertEquals($text[0]->getText(), 'Set as current revision');
    }
    else {
      // Check the last revision is set as current.
      $text = $this->xpath('//tbody/tr[1]/td[4]/em');
      $this->assertEquals($text[0]->getText(), 'Current revision');
      $this->assertSession()->linkNotExists('Set as current revision');
    }
  }

  /**
   * Tests pager on diff overview.
   */
  public function testOverviewPager(): void {
    $this->config('diff.settings')
      ->set('general_settings.revision_pager_limit', 10)
      ->save();

    $this->loginAsAdmin(['view article revisions']);

    $node = $this->drupalCreateNode([
      'type' => 'article',
    ]);

    // Create 11 more revisions in order to trigger paging on the revisions
    // overview screen.
    for ($i = 0; $i < 11; $i++) {
      $edit = [
        'revision' => TRUE,
        'body[0][value]' => 'change: ' . $i,
      ];
      $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');
    }

    // Check the number of elements on the first page.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $element = $this->xpath('//*[@id="edit-node-revisions-table"]/tbody/tr');
    $this->assertCount(10, $element);
    // Check that the pager exists.
    $this->assertSession()->responseContains('page=1');

    $this->clickLink('Next page');
    // Check the number of elements on the second page.
    $element = $this->xpath('//*[@id="edit-node-revisions-table"]/tbody/tr');
    $this->assertCount(2, $element);
    $this->assertSession()->responseContains('page=0');
    $this->clickLink('Previous page');
  }

  /**
   * Tests the revisions overview error messages.
   *
   * @todo Move to DiffLocaleTest?
   */
  public function testRevisionOverviewErrorMessages(): void {
    // Enable some languages for this test.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    // Login as admin with the required permissions.
    $this->loginAsAdmin([
      'administer node form display',
      'administer languages',
      'administer content translation',
      'create content translations',
      'translate any entity',
    ]);

    // Make article content translatable.
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][article][translatable]' => TRUE,
      'settings[node][article][settings][language][language_alterable]' => TRUE,
    ];
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');

    // Create an article.
    $title = 'test_title_b';
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => '<p>Revision 1</p>',
    ];

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle($title);
    $revision1 = $node->getRevisionId();

    // Create a revision, changing the node language to German.
    $edit = [
      'langcode[0][value]' => 'de',
      'body[0][value]' => '<p>Revision 2</p>',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check the revisions overview, ensure only one revisions is available.
    $this->clickLink('Revisions');
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(1, $rows);

    // Compare the revisions and assert the first error message.
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Multiple revisions are needed for comparison.');

    // Create another revision, changing the node language back to English.
    $edit = [
      'langcode[0][value]' => 'en',
      'body[0][value]' => '<p>Revision 3</p>',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle($title, TRUE);
    $revision3 = $node->getRevisionId();

    // Check the revisions overview, ensure two revisions are available.
    $this->clickLink('Revisions');
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(2, $rows);
    $this->assertSession()->checkboxNotChecked('edit-node-revisions-table-0-select-column-one');
    $this->assertSession()->checkboxChecked('edit-node-revisions-table-0-select-column-two');
    $this->assertSession()->checkboxNotChecked('edit-node-revisions-table-1-select-column-one');
    $this->assertSession()->checkboxNotChecked('edit-node-revisions-table-1-select-column-two');

    // Compare the revisions and assert the second error message.
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Select two revisions to compare.');

    // Check the same revisions twice and compare.
    $edit = [
      'radios_left' => $revision3,
      'radios_right' => $revision3,
    ];
    $this->drupalGet('/node/' . $node->id() . '/revisions');
    $this->submitForm($edit, 'Compare selected revisions');
    // Assert the third error message.
    $this->assertSession()->pageTextContains('Select different revisions to compare.');

    // Check different revisions and compare. This time should work correctly.
    $edit = [
      'radios_left' => $revision3,
      'radios_right' => $revision1,
    ];
    $this->drupalGet('/node/' . $node->id() . '/revisions');
    $this->submitForm($edit, 'Compare selected revisions');
    $this->assertSession()->linkByHrefExists('node/' . $node->id() . '/revisions/view/' . $revision1 . '/' . $revision3);
  }

  /**
   * Tests Reference to Deleted Entities.
   */
  public function testEntityReference(): void {
    // Login as admin with the required permissions.
    $this->loginAsAdmin([
      'administer node fields',
    ]);

    // Adding Entity Reference to Article Content Type.
    $this->drupalGet('admin/structure/types/manage/article/fields/add-field');
    $this->submitForm([
      'new_storage_type' => 'reference',
    ], 'Continue');
    $this->submitForm([
      'group_field_options_wrapper' => 'field_ui:entity_reference:node',
      'new_storage_type' => 'reference',
      'label' => 'Content reference test',
      'field_name' => 'content',
    ], 'Continue');
    $this->submitForm([
      'settings[handler_settings][target_bundles][article]' => '1',
    ], 'Save settings');

    // Create an first article.
    $title = 'test_title_c';
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => '<p>First article</p>',
    ];
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $node_one = $this->drupalGetNodeByTitle($title);

    // Create second article.
    $title = 'test_title_d';
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => '<p>Second article</p>',
    ];
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $node_two = $this->drupalGetNodeByTitle($title);

    // Create revision and add entity reference from second node to first.
    $edit = [
      'body[0][value]' => '<p>First Revision</p>',
      'field_content[0][target_id]' => $node_two->getTitle(),
      'revision' => TRUE,
    ];
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalPostNodeForm('node/' . $node_one->id() . '/edit', $edit, 'Save');

    // Delete referenced node.
    $node_two->delete();

    // Access revision of first node.
    $this->drupalGet('/node/' . $node_one->id());
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    // Revision section should appear.
    $this->assertSession()->statusCodeEquals(200);
  }

}

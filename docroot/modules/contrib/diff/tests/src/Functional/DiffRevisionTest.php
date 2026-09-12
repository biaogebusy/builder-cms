<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the diff revisions overview.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
class DiffRevisionTest extends DiffTestBase {

  use AssertBreadcrumbTrait;

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

    // Assert the submit button.
    $this->assertSession()->elementExists('xpath', '//input[@type="submit" and @id="edit-submit" and @value="Compare selected revisions"]');
    $this->assertSession()->elementNotExists('xpath', '//input[@type="submit" and @id="edit-submit-top" and @value="Compare selected revisions"]');

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
    $diff_row = $rows[1]->findAll('xpath', '/td');
    // Assert the revision comment.
    $this->assertSession()->responseContains('diff-revision__item-message">Revision 2 comment');
    // Assert changes made to the body, text 1 changed to 2.
    $this->assertEquals('1', $diff_row[0]->getText());
    $this->assertEquals('-', $diff_row[1]->getText());
    $this->assertEquals('1', $diff_row[2]->find('xpath', 'span')->getText());
    $this->assertEquals('<p>Revision 1</p>', \htmlspecialchars_decode(\strip_tags((string) $diff_row[2]->getHtml())));
    $this->assertEquals('1', $diff_row[3]->getText());
    $this->assertEquals('+', $diff_row[4]->getText());
    $this->assertEquals('2', $diff_row[5]->find('xpath', 'span')->getText());
    $this->assertEquals('<p>Revision 2</p>', \htmlspecialchars_decode((\strip_tags((string) $diff_row[5]->getHtml()))));

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
    $this->assertEquals('-', $diff_row[0]->getText());
    $this->assertEquals('1', $diff_row[1]->find('xpath', 'span')->getText());
    $this->assertEquals('Revision 1', \htmlspecialchars_decode(\trim(\strip_tags((string) $diff_row[1]->getHtml()))));
    $this->assertEquals('+', $diff_row[2]->getText());
    $this->assertEquals('2', $diff_row[3]->find('xpath', 'span')->getText());
    $this->assertEquals('Revision 2', \htmlspecialchars_decode(\trim(\strip_tags((string) $diff_row[3]->getHtml()))));

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
    $this->assertEquals('1', $diff_row[0]->getText());
    $this->assertEquals('', $diff_row[1]->getText());
    $this->assertEquals('-', $diff_row[2]->getText());
    $this->assertEquals('1', $diff_row[3]->find('xpath', 'span')->getText());
    $this->assertEquals('<p>Revision 1</p>', \htmlspecialchars_decode(\strip_tags((string) $diff_row[3]->getHtml())));
    $diff_row = $rows[2]->findAll('xpath', '/td');
    $this->assertEquals('', $diff_row[0]->getText());
    $this->assertEquals('1', $diff_row[1]->getText());
    $this->assertEquals('+', $diff_row[2]->getText());
    $this->assertEquals('2', $diff_row[3]->find('xpath', 'span')->getText());
    $this->assertEquals('<p>Revision 2</p>', \htmlspecialchars_decode(\strip_tags((string) $diff_row[3]->getHtml())));
    $this->assertSession()->pageTextContainsOnce('first_unique_text');
    $this->assertSession()->pageTextContainsOnce('second_unique_text');
    $diff_row = $rows[3]->findAll('xpath', '/td');
    $this->assertEquals('2', $diff_row[0]->getText());
    $this->assertEquals('2', $diff_row[1]->getText());
    $diff_row = $rows[4]->findAll('xpath', '/td');
    $this->assertEquals('3', $diff_row[0]->getText());
    $this->assertEquals('3', $diff_row[1]->getText());

    $this->clickLink('Strip tags');
    // Extract the changes.
    $rows = $this->xpath('//tbody/tr');
    $diff_row = $rows[1]->findAll('xpath', '/td');

    // Assert changes made to the body, with strip_tags filter and make sure
    // there are no line numbers.
    $this->assertEquals('-', $diff_row[0]->getText());
    $this->assertEquals('1', $diff_row[1]->find('xpath', 'span')->getText());
    $this->assertEquals('Revision 1', \htmlspecialchars_decode(\trim(\strip_tags((string) $diff_row[1]->getHtml()))));
    $diff_row = $rows[2]->findAll('xpath', '/td');
    $this->assertEquals('+', $diff_row[0]->getText());
    $this->assertEquals('2', $diff_row[1]->find('xpath', 'span')->getText());
    $this->assertEquals('Revision 2', \htmlspecialchars_decode(\trim(\strip_tags((string) $diff_row[1]->getHtml()))));

    $this->drupalGet($node->toUrl('version-history'));
    // Revert the revision, confirm.
    $this->clickLink('Revert');
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
    $this->drupalGet($node->toUrl('version-history'));
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(1, $rows);

    // Assert that there are no radio buttons for revision selection.
    $this->assertSession()->elementNotExists('xpath', '//input[@type="radio"]');
    // Assert that there is no submit button.
    $this->assertSession()->elementNotExists('xpath', '//input[@type="submit" and @value="Compare selected revisions"]');

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
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    $edit = [
      'title[0][value]' => 'newer test title',
      'body[0][value]' => '<p>newer body</p>',
      'revision_log[0][value]' => 'this revision message will appear twice',
    ];
    // Set to published if content moderation is enabled.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    $this->clickLink('Revisions');
    // Assert the revision summary.
    $page_text = $this->getSession()->getPage()->getText();
    $nr_found = \substr_count((string) $page_text, 'this revision message will appear twice');
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
    $node->body->value = '<p>even newer body</p>';
    $node->setRevisionLogMessage('non default revision message');
    $node->setRevisionTranslationAffectedEnforced(TRUE);
    if ($node->hasField('moderation_state')) {
      // If testing with content_moderation enabled, set as draft.
      $node->moderation_state = 'draft';
    }
    $node->save();
    $this->drupalGet('node/' . $node->id() . '/revisions');

    // Check that the last revision is not the current one.
    $this->assertSession()->linkExists('Set as current revision');
    $text = $this->xpath('//tbody/tr[2]/td[4]/em');
    $this->assertEquals('Current revision', $text[0]->getText());

    // Set the last revision as current.
    $this->clickLink('Set as current revision');
    $this->submitForm([], 'Revert');

    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      // With content moderation, the new revision will not be current.
      // @see https://www.drupal.org/node/2899719
      $text = $this->xpath('//tbody/tr[1]/td[4]/div/div/ul/li/a');
      $this->assertEquals('Set as current revision', $text[0]->getText());
    }
    else {
      // Check the last revision is set as current.
      $text = $this->xpath('//tbody/tr[1]/td[4]/em');
      $this->assertEquals('Current revision', $text[0]->getText());
      $this->assertSession()->linkNotExists('Set as current revision');
    }

    // Make sure there are 5 revisions.
    $this->assertCount(5, $this->xpath('//tbody/tr'));

    // Assert the submit buttons. With 5 revisions, only the bottom one should
    // display.
    $this->assertSession()->elementNotExists('xpath', '//input[@type="submit" and @id="edit-submit-top" and @value="Compare selected revisions"]');
    $this->assertSession()->elementExists('xpath', '//input[@type="submit" and @id="edit-submit" and @value="Compare selected revisions"]');

    // Create another revision and check for the top submit button.
    $this->drupalGet('node/' . $node->id());
    $edit = [
      'body[0][value]' => '<p>More revisions to test the top submit button</p>
      <p>first_unique_text</p>
      <p>second_unique_text</p>',
      'revision' => TRUE,
      'revision_log[0][value]' => 'Revision comment',
    ];
    // Set to published if content moderation is enabled.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $edit['moderation_state[0][state]'] = 'published';
    }
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check the revisions overview.
    $this->drupalGet($node->toUrl('version-history'));
    $rows = $this->xpath('//tbody/tr');
    // Make sure there are 6 revisions.
    $this->assertCount(6, $rows);

    $this->assertSession()->elementExists('xpath', '//input[@type="submit" and @id="edit-submit-top" and @value="Compare selected revisions"]');
    $this->assertSession()->elementExists('xpath', '//input[@type="submit" and @id="edit-submit" and @value="Compare selected revisions"]');
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
      $this->drupalGet($node->toUrl('edit-form'));
      $edit = [
        'revision' => TRUE,
        'body[0][value]' => 'change: ' . $i,
      ];
      $this->submitForm($edit, 'Save');
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

    // No compare button when there's only 1 revision.
    $this->assertSession()->buttonNotExists('Compare selected revisions');

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
    $this->assertSession()->checkboxChecked('edit-node-revisions-table-1-select-column-one');
    $this->assertSession()->checkboxNotChecked('edit-node-revisions-table-1-select-column-two');

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
    $this->loginAsAdmin();

    // Adding Entity Reference to Article Content Type.
    FieldStorageConfig::create([
      'field_name' => 'field_content',
      'entity_type' => 'node',
      'translatable' => FALSE,
      'entity_types' => [],
      'settings' => [
        'target_type' => 'node',
      ],
      'type' => 'entity_reference',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'label' => 'Content reference test',
      'field_name' => 'field_content',
      'entity_type' => 'node',
      'required' => FALSE,
      'bundle' => 'article',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'article',
          ],
        ],
      ],
    ])->save();
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'article')->setComponent('field_content', [
      'type' => 'entity_reference_autocomplete',
    ])->save();

    // Create an first article.
    $values = [
      'title' => 'test_title_c',
      'body' => ['value' => '<p>First article</p>'],
      'type' => 'article',
    ];
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $values['moderation_state'] = 'published';
    }
    $node_one = $this->drupalCreateNode($values);

    // Create second article.
    $values = [
      'title' => 'test_title_d',
      'body' => ['value' => '<p>Second article</p>'],
      'type' => 'article',
    ];
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $values['moderation_state'] = 'published';
    }
    $node_two = $this->drupalCreateNode($values);

    // Create revision and add entity reference from second node to first.
    $node_one->setNewRevision();
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $node_one->set('moderation_state', 'published');
    }
    $node_one->set('field_content', $node_two->id())->save();

    // Delete referenced node.
    $node_two->delete();

    // Access revision of first node.
    $this->drupalGet($node_one->toUrl('version-history'));
    $this->submitForm([], 'Compare selected revisions');
    // Revision section should appear.
    $this->assertSession()->statusCodeEquals(200);
  }

}

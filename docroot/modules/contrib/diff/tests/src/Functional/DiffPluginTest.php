<?php

namespace Drupal\Tests\diff\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the Diff module plugins.
 *
 * @group diff
 */
class DiffPluginTest extends DiffPluginTestBase {

  use CommentTestTrait;
  use CoreVersionUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
  ];

  /**
   * Adds a text field.
   *
   * @param string $field_name
   *   The machine field name.
   * @param string $label
   *   The field label.
   * @param string $field_type
   *   The field type.
   * @param string $widget_type
   *   The widget type.
   */
  protected function addArticleTextField($field_name, $label, $field_type, $widget_type): void {
    // Create a field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $field_type,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => $label,
    ])->save();
    $this->formDisplay->load('node.article.default')
      ->setComponent($field_name, ['type' => $widget_type])
      ->save();
    $this->viewDisplay->load('node.article.default')
      ->setComponent($field_name)
      ->save();
  }

  /**
   * Tests the changed field without plugins.
   */
  public function testFieldWithNoPlugin(): void {
    // Create an article.
    $node = $this->drupalCreateNode([
      'type' => 'article',
    ]);

    // Update the article and add a new revision, the "changed" field should be
    // updated which does not have plugins provided by diff.
    $edit = [
      'revision' => TRUE,
      'body[0][value]' => 'change',
    ];
    $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');

    // Check the difference between the last two revisions.
    $this->clickLink(t('Revisions'));
    $this->submitForm([], 'Compare selected revisions');

    // "changed" field is not displayed since there is no plugin for it. This
    // should not break the revisions comparison display.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Split fields');
  }

  /**
   * Tests the access check for a field while comparing revisions.
   */
  public function testFieldNoAccess(): void {
    // Add a text and a text field to article.
    $this->addArticleTextField('field_diff_deny_access', 'field_diff_deny_access', 'string', 'string_textfield');

    // Create an article.
    $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article access',
      'field_diff_deny_access' => 'Foo',
    ]);

    // Create a revision of the article.
    $node = $this->getNodeByTitle('Test article access');
    $node->setTitle('Test article no access');
    $node->set('field_diff_deny_access', 'Fighters');
    $node->setNewRevision(TRUE);
    $node->save();

    // Check the "Text Field No Access" field is not displayed.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('field_diff_deny_access');
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(2, $rows);
  }

  /**
   * Tests plugin applicability and weight relevance.
   *
   * @covers \Drupal\diff_test\Plugin\diff\Field\TestHeavierTextPlugin
   * @covers \Drupal\diff_test\Plugin\diff\Field\TestLighterTextPlugin
   */
  public function testApplicablePlugin(): void {
    // Add three text fields to the article.
    $this->addArticleTextField('test_field', 'Test Applicable', 'text', 'text_textfield');
    $this->addArticleTextField('test_field_lighter', 'Test Lighter Applicable', 'text', 'text_textfield');
    $this->addArticleTextField('test_field_non_applicable', 'Test Not Applicable', 'text', 'text_textfield');

    // Create an article, setting values on fields.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article',
      'test_field' => 'first_nice_applicable',
      'test_field_lighter' => 'second_nice_applicable',
      'test_field_non_applicable' => 'not_applicable',
    ]);

    // Edit the article and update these fields, creating a new revision.
    $edit = [
      'test_field[0][value]' => 'first_nicer_applicable',
      'test_field_lighter[0][value]' => 'second_nicer_applicable',
      'test_field_non_applicable[0][value]' => 'nicer_not_applicable',
      'revision' => TRUE,
    ];
    $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');

    // Check differences between revisions.
    $this->clickLink(t('Revisions'));
    $this->submitForm([], 'Compare selected revisions');

    // Check diff for an applicable field of testTextPlugin.
    $this->assertSession()->pageTextContains('Test Applicable');
    $this->assertSession()->pageTextContains('first_nice_heavier_test_plugin');
    $this->assertSession()->pageTextContains('first_nicer_heavier_test_plugin');

    // Check diff for an applicable field of testTextPlugin and
    // testLighterTextPlugin. The plugin selected for this field should be the
    // lightest one.
    $this->assertSession()->pageTextContains('Test Lighter Applicable');
    $this->assertSession()->pageTextContains('second_nice_lighter_test_plugin');
    $this->assertSession()->pageTextContains('second_nicer_lighter_test_plugin');

    // Check diff for a non applicable field of both test plugins.
    $this->assertSession()->pageTextContains('Test Not Applicable');
    $this->assertSession()->pageTextContains('not_applicable');
    $this->assertSession()->pageTextContains('nicer_not_applicable');
  }

  /**
   * Tests field content trimming.
   */
  public function testTrimmingField(): void {
    // Create a node.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'test_trim',
      'body' => '<p>body</p>',
    ]);
    // Save a second revision.
    $node->save();

    // Create a revision adding a new empty line to the body.
    $node = $this->drupalGetNodeByTitle('test_trim');
    $edit = [
      'revision' => TRUE,
      'body[0][value]' => '<p>body</p>
',
    ];
    $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');

    // Assert the revision comparison.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextNotContains('No visible changes.');
    $rows = $this->xpath('//tbody/tr');
    $diff_row = $rows[1]->findAll('xpath', '/td');
    $this->assertCount(3, $rows);
    $this->assertEquals(htmlspecialchars_decode(strip_tags($diff_row[2]->getHtml())), '<p>body</p>');

    // Create a new revision and update the body.
    $edit = [
      'revision' => TRUE,
      'body[0][value]' => '<p>body</p>

<p>body_new</p>
',
    ];
    $this->drupalPostNodeForm('node/' . $node->id() . '/edit', $edit, 'Save');
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextNotContains('No visible changes.');
    $rows = $this->xpath('//tbody/tr');
    // The diff engine in Drupal core 10.1 changed so we have different amounts
    // of rows before and after that engine change. The important thing is that
    // multiple rows show and all of them, including empty lines, display line
    // numbers.
    $this->assertGreaterThan(3, count($rows));
    $this->assertEquals(htmlspecialchars_decode(strip_tags($rows[1]->findAll('xpath', '/td')[3]->getHtml())), '1');
    $this->assertEquals(htmlspecialchars_decode(strip_tags($rows[2]->findAll('xpath', '/td')[3]->getHtml())), '2');
    $this->assertEquals(htmlspecialchars_decode(strip_tags($rows[3]->findAll('xpath', '/td')[3]->getHtml())), '3');
  }

}

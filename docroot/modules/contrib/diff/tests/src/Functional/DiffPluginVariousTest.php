<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Diff module plugins.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
class DiffPluginVariousTest extends DiffPluginTestBase {

  use CommentTestTrait;

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
    \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.article.default')
      ->setComponent($field_name, ['type' => $widget_type])
      ->save();
    \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.article.default')
      ->setComponent($field_name)
      ->save();
  }

  /**
   * Tests the comment plugin.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\CommentFieldBuilder
   */
  public function testCommentPlugin(): void {
    // Add the comment field to articles.
    $this->addDefaultCommentField('node', 'article');

    // Create an article with comments enabled..
    $title = 'Sample article';
    $this->drupalGet('node/add/article');
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => '<p>Revision 1</p>',
      // @phpstan-ignore-next-line
      'comment[0][status]' => (string) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', static fn() => CommentingStatus::Open->value, static fn() => CommentItemInterface::OPEN),
    ];
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle($title);

    // Edit the article and close its comments.
    $this->drupalGet($node->toUrl('edit-form'));
    $edit = [
      // @phpstan-ignore-next-line
      'comment[0][status]' => (string) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', static fn() => CommentingStatus::Hidden->value, static fn() => CommentItemInterface::HIDDEN),
      'revision' => TRUE,
    ];
    $this->submitForm($edit, 'Save');

    // Check the difference between the last two revisions.
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Comments');
    $this->assertSession()->pageTextContains('Comments for this entity are open.');
    $this->assertSession()->pageTextContains('Comments for this entity are hidden.');
  }

  /**
   * Tests the Core plugin.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\CoreFieldBuilder
   */
  public function testCorePlugin(): void {
    // Add an email field (supported by the Diff core plugin) to the Article
    // content type.
    $field_name = 'field_email';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'email',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Email',
    ])->save();

    // Add the email field to the article form.
    $this->loadFormDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'email_default'])
      ->save();

    // Add the email field to the default display.
    $this->loadViewDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'basic_string'])
      ->save();

    // Create an article with an email.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_email' => 'foo@example.com',
    ]);

    // Edit the article and change the email.
    $edit = [
      'field_email[0][value]' => 'bar@example.com',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check the difference between the last two revisions.
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Email');
    $this->assertSession()->pageTextContains('foo@example.com');
    $this->assertSession()->pageTextContains('bar@example.com');
  }

  /**
   * Tests the Core plugin with a timestamp field.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\CoreFieldBuilder
   */
  public function testCorePluginTimestampField(): void {
    // Add a timestamp field (supported by the Diff core plugin) to the Article
    // content type.
    $field_name = 'field_timestamp';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'timestamp',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Timestamp test',
    ])->save();

    // Add the timestamp field to the article form.
    $this->loadFormDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'datetime_timestamp'])
      ->save();

    // Add the timestamp field to the default display.
    $this->loadViewDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'timestamp'])
      ->save();

    $old_timestamp = '321321321';
    $new_timestamp = '123123123';

    // Create an article with an timestamp.
    $this->drupalCreateNode([
      'title' => 'timestamp_test',
      'type' => 'article',
      'field_timestamp' => $old_timestamp,
    ]);

    // Create a new revision with an updated timestamp.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->drupalGetNodeByTitle('timestamp_test');
    $node->set('field_timestamp', $new_timestamp);
    $node->setNewRevision(TRUE);
    $node->save();

    // Compare the revisions.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');

    // Assert that the timestamp field does not show a unix time format.
    $this->assertSession()->pageTextContains('Timestamp test');
    $date_formatter = \Drupal::service('date.formatter');
    $this->assertSession()->pageTextContains($date_formatter->format($old_timestamp));
    $this->assertSession()->pageTextContains($date_formatter->format($new_timestamp));
  }

  /**
   * Tests the Link plugin.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\LinkFieldBuilder
   */
  public function testLinkPlugin(): void {
    // Add a link field to the article content type.
    $field_name = 'field_link';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'link',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Link',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ])->save();
    $this->loadFormDisplay('node.article.default')
      ->setComponent($field_name, [
        'type' => 'link_default',
        'settings' => [
          'placeholder_url' => 'http://example.com',
        ],
      ])
      ->save();
    $this->loadViewDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'link'])
      ->save();

    // Enable the comparison of the link's title field.
    $this->config('diff.plugins')
      ->set('fields.node.field_link.type', 'link_field_diff_builder')
      ->set('fields.node.field_link.settings', ['compare_title' => TRUE])
      ->save();

    // Create an article, setting values on the link field.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article',
      'field_link' => [
        'title' => 'Google',
        'uri' => 'http://www.google.com',
      ],
    ]);

    // Update the link field.
    $edit = [
      'field_link[0][title]' => 'Guguel',
      'field_link[0][uri]' => 'http://www.google.es',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check differences between revisions.
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Link');
    $this->assertSession()->pageTextContains('Google');
    $this->assertSession()->pageTextContains('http://www.google.com');
    $this->assertSession()->pageTextContains('Guguel');
    $this->assertSession()->pageTextContains('http://www.google.es');
  }

  /**
   * Tests the List plugin.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\ListFieldBuilder
   */
  public function testListPlugin(): void {
    // Add a list field to the article content type.
    $field_name = 'field_list';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'list_string',
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => [
          'value_a' => 'Value A',
          'value_b' => 'Value B',
        ],
      ],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'label' => 'List',
    ])->save();

    $this->loadFormDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'options_select'])
      ->save();
    $this->loadViewDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'list_default'])
      ->save();

    // Create an article, setting values on the lit field.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article',
      'field_list' => 'value_a',
    ]);

    // Update the list field.
    $edit = [
      'field_list' => 'value_b',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check differences between revisions.
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('List');
    $this->assertSession()->pageTextContains('value_a');
    $this->assertSession()->pageTextContains('value_b');
  }

  /**
   * Tests the Text plugin.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\TextFieldBuilder
   */
  public function testTextPlugin(): void {
    // Add a text and a text long field to the Article content type.
    $this->addArticleTextField('field_text', 'Text Field', 'string', 'string_textfield');
    $this->addArticleTextField('field_text_long', 'Text Long Field', 'string_long', 'string_textarea');

    // Create an article, setting values on both fields.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article',
      'field_text' => 'Foo',
      'field_text_long' => 'Fighters',
    ]);

    // Edit the article and update these fields, creating a new revision.
    $edit = [
      'field_text[0][value]' => 'Bar',
      'field_text_long[0][value]' => 'Fly',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check differences between revisions.
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Text Field');
    $this->assertSession()->pageTextContains('Text Long Field');
    $this->assertSession()->pageTextContains('Foo');
    $this->assertSession()->pageTextContains('Fighters');
    $this->assertSession()->pageTextContains('Bar');
    $this->assertSession()->pageTextContains('Fly');
  }

  /**
   * Tests the TextWithSummary plugin.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\TextWithSummaryFieldBuilder
   */
  public function testTextWithSummaryPlugin(): void {
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'body_summary',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
      'cardinality' => 1,
      'persist_with_no_fields' => TRUE,
    ]);
    $fieldStorage->save();
    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
      'label' => 'Body with summary',
      'settings' => [
        'allowed_formats' => [],
      ],
    ])->save();
    $display_repository = \Drupal::service(EntityDisplayRepositoryInterface::class);

    // Assign widget settings for the default form mode.
    $display_repository->getFormDisplay('node', 'article')->setComponent('body_summary', [
      'type' => 'text_textarea_with_summary',
    ])->save();
    // Enable the comparison of the summary.
    $config = \Drupal::configFactory()->getEditable('diff.plugins');
    $settings['compare_summary'] = TRUE;
    $config->set('fields.node.body_summary.type', 'text_summary_field_diff_builder');
    $config->set('fields.node.body_summary.settings', $settings);
    $config->save();

    // Create an article, setting the body field.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article',
      'body_summary' => [
        'value' => 'Foo value',
        'summary' => 'Foo summary',
      ],
    ]);

    // Edit the article and update these fields, creating a new revision.
    $edit = [
      'body_summary[0][value]' => 'Bar value',
      'body_summary[0][summary]' => 'Bar summary',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check differences between revisions.
    $this->clickLink('Revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextContains('Body');
    $this->assertSession()->pageTextContains('Foo value');
    $this->assertSession()->pageTextContains('Foo summary');
    $this->assertSession()->pageTextContains('Bar value');
    $this->assertSession()->pageTextContains('Bar summary');
  }

}

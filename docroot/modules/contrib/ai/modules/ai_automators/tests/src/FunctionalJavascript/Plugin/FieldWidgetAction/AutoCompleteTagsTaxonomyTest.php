<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\FunctionalJavascript\Plugin\FieldWidgetAction;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the field widget action for AutoCompleteTagsTaxonomy.
 *
 * @group ai_automators
 * @group 3577469
 */
class AutoCompleteTagsTaxonomyTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'node',
    'file',
    'ai_automators',
    'field',
    'user',
    'text',
    'field_ui',
    'field_widget_actions',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_automators';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a vocabulary for testing.
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'tags',
      'description' => 'Vocabulary for testing tags.',
    ]);
    $vocabulary->save();

    // Create a content type for testing.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create a field for the vocabulary.
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => -1,
    ])->save();

    // Field instance (on the article bundle).
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'auto_create' => TRUE,
          'target_bundles' => [
            'tags' => 'tags',
          ],
        ],
      ],
    ])->save();

    // Create an automator for the field widget action.
    $config_path = __DIR__ . '/../../../../config/autocomplete_tags_taxonomy_test/';
    $data = Yaml::parseFile($config_path . 'ai_automators.ai_automator.node.article.field_tags.default.yml');
    \Drupal::entityTypeManager()
      ->getStorage('ai_automator')
      ->create($data)
      ->save();

    // Create a field form widget action for the AutoCompleteTagsTaxonomy.
    $data = Yaml::parseFile($config_path . 'core.entity_form_display.node.article.default.yml');
    // Remove the display first, as we will create it.
    if (\Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.article.default')) {
      \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.article.default')->delete();
    }
    // Create the whole display config from YAML.
    $entity_form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->create($data);
    $entity_form_display->save();
  }

  /**
   * Tests to create tags using a field widget action.
   */
  public function testCreateTagsFieldWidgetAction(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'administer nodes',
      'administer taxonomy',
      'access content',
      'create article content',
      'edit any article content',
    ]);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/article');
    // Take a screenshot before interaction.
    $this->takeScreenshot('1_initial_form');

    // Get the page.
    $page = $this->getSession()->getPage();

    // Fill in the title.
    $page->fillField('title[0][value]', 'Test Article with Tags');

    // Fill in the body.
    $page->fillField('body[0][value]', 'Monkeys are cool animals');

    // Take a screenshot after filling the form.
    $this->takeScreenshot('2_filled_form');

    // Press the add tags button.
    $this->click('.field-widget-action-automator_autocomplete_tags_on_taxonomy');

    // Take a screenshot after clicking the add tags button.
    $this->takeScreenshot('3_after_add_tags_button');

    // Wait for ajax to complete.
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Take a screenshot after the ajax call.
    $this->takeScreenshot('4_after_ajax_call');

    // Check so the tag monkeys is present in the field.
    $this->assertSession()->fieldValueEquals('field_tags[target_id]', 'monkeys (1)');
  }

}

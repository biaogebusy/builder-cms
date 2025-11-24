<?php

namespace Drupal\Tests\unique_content_field_validation\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test to unique field validation.
 *
 * @group unique_content_field_validation
 */
class EntityFieldUniqueValidationTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'field_test',
    'field_ui',
    'unique_content_field_validation',
  ];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    // Create Basic page node type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    // Create test field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'text',
      'settings' => [],
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => 'test field',
    ]);
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique', TRUE);
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique_text', '');
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique_multivalue', TRUE);
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique_multivalue_text', '');
    $instance->save();

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
    $view_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('node.page.default');

    // Set the field visible on the display object.
    $view_display_options = [
      'type' => 'text_default',
      'label' => 'above',
    ];
    $view_display->setComponent('field_test', $view_display_options);

    // Save display.
    $view_display->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.page.default');

    // Set the field visible on the display object.
    $form_display_options = [
      'type' => 'text_textfield',
      'region' => 'content',
      'settings' => [
        'size' => 10,
      ],
    ];
    $form_display->setComponent('field_test', $form_display_options);

    // Save display.
    $form_display->save();

    // Create a node.
    $node_values = ['type' => 'page'];
    $node_values['title'] = 'First page';
    // Assign a test value for the field.
    $node_values['field_test'][0]['value'] = 'test value';

    $this->node = $this->drupalCreateNode($node_values);
  }

  /**
   * Tests for unique field validation.
   */
  public function testUniqueFieldValidation() {
    $this->drupalGet('node/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('node/add/page');

    // Check that the node exists in the database.
    $node = $this->drupalGetNodeByTitle('First page');
    $this->assertNotEmpty($node, 'Node found in database.');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Second page';
    $edit['field_test[0][value]'] = 'test value';
    $this->drupalGet('node/add/page');
    $this->submitForm($edit, $this->t('Save'));

    // Check that the Basic page has been created.
    $this->assertSession()->pageTextContains(
      $this->t('@label must be unique but "@value" already exists!',
        ['@label' => 'test field', '@value' => $edit['field_test[0][value]']]));
  }

}

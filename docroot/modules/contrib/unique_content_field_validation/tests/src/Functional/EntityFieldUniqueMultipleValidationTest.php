<?php

namespace Drupal\Tests\unique_content_field_validation\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test when a multi-value field has to be unique value in each item.
 *
 * @group unique_content_field_validation
 */
class EntityFieldUniqueMultipleValidationTest extends BrowserTestBase {

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
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [],
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => $this->randomMachineName(),
    ]);
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique', FALSE);
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique_text', '');
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique_multivalue', TRUE);
    $instance->setThirdPartySetting('unique_content_field_validation', 'unique_multivalue_text', 'Value is already set and each value needs to be unique');
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
  }

  /**
   * Tests for unique field validation.
   */
  public function testUniqueFieldValidation() {
    $this->drupalGet('node/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('node/add/page');

    // Create a node.
    $edit = [];
    $this->drupalGet('node/add/page');
    $this->submitForm($edit, 'Add another item');
    $edit['title[0][value]'] = 'Multiple validation same field page';
    $edit['field_test[0][value]'] = 'test value';
    $edit['field_test[1][value]'] = 'test value';
    $this->submitForm($edit, $this->t('Save'));

    // Check that the Basic page has been created.
    $this->assertSession()->pageTextContains($this->t('Value is already set and each value needs to be unique'));
  }

}

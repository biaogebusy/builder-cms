<?php

namespace Drupal\Tests\allowed_formats\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Tests the basic functionality of Allowed Formats.
 *
 * @group allowed_formats
 */
class AllowedFormatsTest extends BrowserTestBase {

  // Provides shortcut method createVocabulary().
  use TaxonomyTestTrait;
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
  public static $modules = [
    'entity_test',
    'allowed_formats',
    'field_ui',
    'taxonomy',
  ];

  /**
   * A user with relevant administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with privileges to edit a text field.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer filters',
      'administer entity_test fields',
    ]);
    $this->webUser = $this->drupalCreateUser([
      'administer entity_test content',
      'administer taxonomy',
    ]);
  }

  /**
   * Test widgets for fields with selected allowed formats.
   */
  public function testAllowedFormats() {

    // Create one text format.
    $format1 = FilterFormat::create([
      'format' => mb_strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
      'roles' => [$this->webUser->getRoles()[0]],
    ]);
    $format1->save();

    // Create a second text format.
    $format2 = FilterFormat::create([
      'format' => mb_strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
      'roles' => [$this->webUser->getRoles()[0]],
    ]);
    $format2->save();

    // Change the Allowed Formats settings of the test field created by
    // entity_test_install().
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.field_test_text');
    $this->submitForm([
      'third_party_settings[allowed_formats][allowed_formats][' . $format1->id() . ']' => TRUE,
      'third_party_settings[allowed_formats][allowed_formats][' . $format2->id() . ']' => TRUE,
    ], $this->t('Save settings'));

    // Display the creation form.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldExists("field_test_text[0][value]");
    $this->assertSession()->fieldExists("field_test_text[0][format]");

    // Change field to allow only one format.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.field_test_text');
    $this->submitForm([
      'third_party_settings[allowed_formats][allowed_formats][' . $format2->id() . ']' => FALSE,
    ], $this->t('Save settings'));

    // We shouldn't have the 'format' selector since only one format is allowed.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldExists("field_test_text[0][value]");
    $this->assertSession()->fieldNotExists("field_test_text[0][format]");
  }

  /**
   * Test limiting allowed formats on base fields.
   */
  public function testBaseFields() {
    // Create a vocabulary.
    $vocabulary = $this->createVocabulary();

    // Create the text formats as configured for the taxonomy term description
    // field.
    $roles = [$this->webUser->getRoles()[0]];
    $format1 = FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'basic_html',
      'roles' => $roles,
    ]);
    $format1->save();
    $format2 = FilterFormat::create([
      'format' => 'restricted_html',
      'name' => 'restricted_html',
      'roles' => $roles,
    ]);
    $format2->save();
    $format3 = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'full_html',
      'roles' => $roles,
    ]);
    $format3->save();

    // Display the term creation form, we expect the widget to be displayed,
    // and the formats 'basic_html', 'restricted_html' and 'full_html' to be
    // available.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $this->assertSession()->fieldExists("description[0][value]");
    $this->assertSession()->fieldExists("description[0][format]");
    $this->assertSession()->optionExists('edit-description-0-format--2', 'basic_html');
    $this->assertSession()->optionExists('edit-description-0-format--2', 'restricted_html');
    $this->assertSession()->optionExists('edit-description-0-format--2', 'full_html');

    // Enable our test module, which disallows using the 'full_html' format
    // using the allowed_formats functionality.
    \Drupal::service('module_installer')->install(['allowed_formats_base_fields_test']);

    // Display the term creation form again and check that 'full_html' is
    // not available as expected.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add');
    $this->assertSession()->fieldExists("description[0][value]");
    $this->assertSession()->fieldExists("description[0][format]");
    $this->assertSession()->optionExists('edit-description-0-format--2', 'basic_html');
    $this->assertSession()->optionExists('edit-description-0-format--2', 'restricted_html');
    $this->assertSession()->optionNotExists('edit-description-0-format--2', 'full_html');
  }

}

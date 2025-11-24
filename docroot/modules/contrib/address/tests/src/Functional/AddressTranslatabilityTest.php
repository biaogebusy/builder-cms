<?php

namespace Drupal\Tests\address\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Address field translatability.
 *
 * @group address
 */
class AddressTranslatabilityTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'address',
    'language',
    'content_translation',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the "Basic page" node type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Create some languages.
    ConfigurableLanguage::create(['id' => 'fr'])->save();
    ConfigurableLanguage::create(['id' => 'it'])->save();

    // Create the address field on the "Basic page" node type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'node',
      'type' => 'address',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => 'Address',
    ]);
    $field->save();
  }

  /**
   * Tests synced address fields on translated nodes.
   */
  public function testSyncedAddressFields() {
    $user = $this->drupalCreateUser([], NULL, TRUE);

    $this->drupalLogin($user);

    // Enable translation for "Basic page" nodes.
    $edit = [
      'entity_types[node]' => 1,
      'settings[node][page][translatable]' => 1,
      "settings[node][page][fields][field_address]" => 1,
      // Disable some fields for translation, as the rest are by default
      // enabled.
      "settings[node][page][columns][field_address][country_code]" => FALSE,
      "settings[node][page][columns][field_address][postal_code]" => FALSE,
    ];
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');

    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = FieldConfig::load('node.page.field_address');
    $sync = $field->getThirdPartySetting('content_translation', 'translation_sync');
    $this->assertEquals([
      'langcode' => 'langcode',
      'administrative_area' => 'administrative_area',
      'locality' => 'locality',
      'dependent_locality' => 'dependent_locality',
      'sorting_code' => 'sorting_code',
      'address_line1' => 'address_line1',
      'address_line2' => 'address_line2',
      'address_line3' => 'address_line3',
      'organization' => 'organization',
      'given_name' => 'given_name',
      'additional_name' => 'additional_name',
      'family_name' => 'family_name',
      'country_code' => "0",
      'postal_code' => "0",
    ], $sync);
  }

}

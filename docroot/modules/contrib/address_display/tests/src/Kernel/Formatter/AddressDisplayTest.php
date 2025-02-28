<?php

namespace Drupal\Tests\address_display\Kernel\Formatter;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the address_display_formatter formatter.
 *
 * @group address_display
 */
class AddressDisplayTest extends KernelTestBase {

  /**
   * Disable strict config schema.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'entity_test',
    'user',
    'address',
    'address_display',
  ];

  /**
   * The tested field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The entity display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * The default display settings to use for the formatters.
   *
   * @var array
   */
  protected $defaultDisplaySettings = [
    'address_display' => [
      'organization' => [
        'display' => TRUE,
        'glue' => '',
        'weight' => -1,
      ],
      'address_line1' => [
        'display' => TRUE,
        'glue' => '',
        'weight' => 0,
      ],
      'address_line2' => [
        'display' => TRUE,
        'glue' => ',',
        'weight' => 1,
      ],
      'locality' => [
        'display' => TRUE,
        'glue' => ',',
        'weight' => 2,
      ],
      'postal_code' => [
        'display' => TRUE,
        'glue' => '',
        'weight' => 3,
      ],
      'country_code' => [
        'display' => TRUE,
        'glue' => '',
        'weight' => 4,
      ],
      'langcode' => [
        'display' => FALSE,
        'glue' => ',',
        'weight' => 100,
      ],
      'administrative_area' => [
        'display' => FALSE,
        'glue' => ',',
        'weight' => 100,
      ],
      'dependent_locality' => [
        'display' => FALSE,
        'glue' => ',',
        'weight' => 100,
      ],
      'sorting_code' => [
        'display' => FALSE,
        'glue' => ',',
        'weight' => 100,
      ],
      'given_name' => [
        'display' => TRUE,
        'glue' => '',
        'weight' => 100,
      ],
      'family_name' => [
        'display' => TRUE,
        'glue' => ',',
        'weight' => 100,
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installConfig(['field']);
    $this->installConfig(['text']);
    $this->installConfig(['address']);
    $this->installConfig(['entity_test']);
    $this->installEntitySchema('entity_test');

    $this->fieldName = mb_strtolower($this->randomMachineName());

    // Create an entity_test field of the 'address' type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'type' => 'address',
    ]);
    $field_storage->save();

    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => $this->randomMachineName(),
    ]);
    $field_config->save();

    // The entity_get_display() procedural function is deprecated in
    // drupal:8.8.0 and above.
    if (version_compare(\Drupal::VERSION, '8.8', '<')) {
      $this->display = entity_get_display('entity_test', 'entity_test', 'default');
    }
    else {
      $this->display = \Drupal::service('entity_display.repository')
        ->getViewDisplay('entity_test', 'entity_test', 'default');
    }
  }

  /**
   * Tests rendering of address components.
   */
  public function testRenderAddressComponents() {
    $entity = EntityTest::create([]);
    $entity->{$this->fieldName} = [
      'organization' => 'Test company',
      'country_code' => 'PL',
      'locality' => 'Kraków',
      'postal_code' => '31-042',
      'address_line1' => 'Rynek Główny 1/3',
      'given_name' => 'Jan',
      'family_name' => 'Nowak',
    ];

    // Tests default settings.
    $this->display->setComponent($this->fieldName, [
      'type' => 'address_display_formatter',
      'settings' => [],
    ]);
    $this->display->save();

    // Render entity fields.
    $content = $this->display->build($entity);
    $this->render($content);

    $expected = implode("\n", [
      '<span class="address-display-element organization-element">Test company</span>',
      '<span class="address-display-element address-line1-element">Rynek Główny 1/3</span>',
      '<span class="address-display-element locality-element">Kraków,</span>',
      '<span class="address-display-element postal-code-element">31-042</span>',
      '<span class="address-display-element country-code-element">Poland</span>',
      '<span class="address-display-element given-name-element">Jan</span>',
      '<span class="address-display-element family-name-element">Nowak</span>',
    ]);
    $this->assertRaw($expected);

    // Tests 'display' option.
    $settings = $this->defaultDisplaySettings;
    $settings['address_display']['country_code']['display'] = FALSE;
    $settings['address_display']['given_name']['display'] = FALSE;
    $settings['address_display']['family_name']['display'] = FALSE;

    $this->display->setComponent($this->fieldName, [
      'type' => 'address_display_formatter',
      'settings' => $settings,
    ]);
    $this->display->save();

    // Render entity fields.
    $content = $this->display->build($entity);
    $this->render($content);

    $expected = implode("\n", [
      '<span class="address-display-element organization-element">Test company</span>',
      '<span class="address-display-element address-line1-element">Rynek Główny 1/3</span>',
      '<span class="address-display-element locality-element">Kraków,</span>',
      '<span class="address-display-element postal-code-element">31-042</span>',
    ]);
    $this->assertRaw($expected);

    // Skip hidden items.
    $this->assertNoRaw('<span class="address-display-element country-code-element">Poland</span>');
    $this->assertNoRaw('<span class="address-display-element given-name-element">Jan</span>');
    $this->assertNoRaw('<span class="address-display-element family-name-element">Nowak</span>');

    // Tests 'glue' option.
    $settings = $this->defaultDisplaySettings;
    $settings['address_display']['organization']['glue'] = ':';
    $settings['address_display']['locality']['glue'] = '';
    $settings['address_display']['family_name']['glue'] = ',';

    $this->display->setComponent($this->fieldName, [
      'type' => 'address_display_formatter',
      'settings' => $settings,
    ]);
    $this->display->save();

    // Render entity fields.
    $content = $this->display->build($entity);
    $this->render($content);

    $expected = implode("\n", [
      '<span class="address-display-element organization-element">Test company:</span>',
      '<span class="address-display-element address-line1-element">Rynek Główny 1/3</span>',
      '<span class="address-display-element locality-element">Kraków</span>',
      '<span class="address-display-element postal-code-element">31-042</span>',
      '<span class="address-display-element country-code-element">Poland</span>',
      '<span class="address-display-element given-name-element">Jan</span>',
      // Don't display the last item separator.
      '<span class="address-display-element family-name-element">Nowak</span>',
    ]);
    $this->assertRaw($expected);
  }

}

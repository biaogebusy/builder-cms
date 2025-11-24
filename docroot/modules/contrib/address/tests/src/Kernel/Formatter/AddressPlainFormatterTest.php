<?php

namespace Drupal\Tests\address\Kernel\Formatter;

use Drupal\entity_test\Entity\EntityTestMul;

/**
 * Tests the address_plain formatter.
 *
 * @group address
 */
class AddressPlainFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createField('address', 'address_plain');
  }

  /**
   * Tests the rendered output.
   *
   * @dataProvider renderDataProvider
   */
  public function testRender($value, $expected_elements) {
    $entity = EntityTestMul::create([]);
    $entity->{$this->fieldName} = $value;
    $this->renderEntityFields($entity, $this->display);

    // Confirm the expected elements.
    foreach ($expected_elements as $expected_element) {
      $this->assertRaw($expected_element);
    }
  }

  /**
   * Data provider for plain formatter output test.
   */
  public function renderDataProvider(): array {
    return [
      // Regular address.
      [
        [
          'country_code' => 'AD',
          'locality' => 'Canillo',
          'postal_code' => 'AD500',
          'address_line1' => 'C. Prat de la Creu, 62-64',
        ],
        [
          'C. Prat de la Creu, 62-64',
          'AD500',
          'Canillo',
          'Andorra',
        ],
      ],
      // Only country and admin area.
      [
        [
          'country_code' => 'US',
          'administrative_area' => 'CA',
        ],
        [
          'CA',
          'United States',
        ],
      ],
      // Only country and locality.
      [
        [
          'country_code' => 'US',
          'locality' => 'San Francisco',
        ],
        [
          'San Francisco',
          'United States',
        ],
      ],
      // Only country and postal code.
      [
        [
          'country_code' => 'US',
          'postal_code' => '94103',
        ],
        [
          '94103',
          'United States',
        ],
      ],
    ];
  }

  /**
   * Confirm that an unrecognized locality is shown unmodified.
   */
  public function testFakeLocality() {
    $entity = EntityTestMul::create([]);
    $entity->{$this->fieldName} = [
      'country_code' => 'AD',
      'locality' => 'FAKE_LOCALITY',
      'postal_code' => 'AD500',
      'address_line1' => 'C. Prat de la Creu, 62-64',
    ];
    $this->renderEntityFields($entity, $this->display);
    $this->assertRaw('FAKE_LOCALITY');
  }

  /**
   * Tests the theme hook suggestions.
   *
   * @see \Drupal\Tests\node\Functional\NodeTemplateSuggestionsTest
   */
  public function testAddressPlainThemeHookSuggestions() {
    $entity = EntityTestMul::create([]);
    $entity->{$this->fieldName} = [
      'country_code' => 'AD',
      'locality' => 'Canillo',
      'postal_code' => 'AD500',
      'address_line1' => 'C. Prat de la Creu, 62-64',
    ];
    foreach (['full', 'my_custom_view_mode'] as $view_mode) {
      // Simulate themeing of the address test entity.
      $variables['theme_hook_original'] = 'address_plain';
      $variables['view_mode'] = $view_mode;
      $variables['address'] = $entity->{$this->fieldName}->get(0);
      $suggestions = \Drupal::moduleHandler()->invokeAll('theme_suggestions_address_plain', [$variables]);

      $expected_suggestions = [
        // Hook __ entity_type __ view_mode.
        'address_plain__entity_test_mul__' . $view_mode,
        // Hook __ entity_type __ bundle.
        'address_plain__entity_test_mul__entity_test_mul',
        // Hook __ entity_type __ bundle __ view_mode.
        'address_plain__entity_test_mul__entity_test_mul__' . $view_mode,
        // Hook __ field_name.
        'address_plain__' . $this->fieldName,
        // Hook __ entity_type __ field_name.
        'address_plain__entity_test_mul__' . $this->fieldName,
        // Hook __ entity_type __ field_name __ bundle.
        'address_plain__entity_test_mul__' . $this->fieldName . '__entity_test_mul',
      ];
      $this->assertEquals($expected_suggestions, $suggestions, 'Unexpected theme suggestions for ' . $view_mode);
    }
  }

}

<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\range\Traits\RangeTestTrait;

/**
 * Base class for range functional integration tests.
 */
abstract class FormatterTestBase extends KernelTestBase {

  use RangeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'entity_test',
    'user',
    'range',
  ];

  /**
   * Field type to test against.
   *
   * @var string
   */
  protected $fieldType;

  /**
   * Field name to test against.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Display type to test.
   *
   * @var string
   */
  protected $displayType;

  /**
   * Display type settings.
   *
   * @var array
   */
  protected $defaultSettings;

  /**
   * Entity, used for testing.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installConfig(['field']);
    $this->installConfig(['text']);
    $this->installConfig(['range']);
    $this->installEntitySchema('entity_test');

    $this->fieldName = $this->getFieldName($this->fieldType);
    $this->createField($this->fieldType);
    $this->createViewDisplay();

    $this->entity = EntityTest::create([]);
  }

  /**
   * Tests formatter.
   */
  public function testFieldFormatter() {
    // PHPUnit @dataProvider is calling setUp()/tearDown() with each data set
    // causing tests to be up to 20x slower.
    foreach ($this->formatterDataProvider() as $formatter_data) {
      $this->assertFieldFormatter(...$formatter_data);
    }
  }

  /**
   * Asserts that field formatter does its job.
   */
  protected function assertFieldFormatter(array $display_settings, $from, $to, $expected) {
    $this->entity->{$this->fieldName} = [
      'from' => $from,
      'to' => $to,
    ];

    $content = $this->entity->{$this->fieldName}->get(0)->view([
      'type' => $this->displayType,
      'settings' => $display_settings,
    ]);
    $renderer = $this->container->get('renderer');
    $this->assertEquals($expected, $renderer->renderRoot($content));
  }

  /**
   * Formatter settings data provider.
   *
   * @return array
   *   Nested arrays of values to check:
   *     - $display_settings
   *     - $from
   *     - $to
   *     - $expected
   */
  protected function formatterDataProvider() {
    // Loop over the specific formatter settings.
    foreach ($this->fieldFormatterDataProvider() as $formatter_data) {
      [$settings, $from, $to, $expected_from, $expected_to] = $formatter_data;

      // Loop over the base formatter settings.
      foreach ($this->fieldFormatterBaseDataProvider() as $formatter_base_data) {
        [$base_settings, $expected_format_separate, $expected_format_combined] = $formatter_base_data;
        $display_settings = $settings + $base_settings + $this->defaultSettings;
        $expected_format = $expected_from !== $expected_to ? $expected_format_separate : $expected_format_combined;
        yield [
          $display_settings,
          $from, $to,
          sprintf($expected_format, $expected_from, $expected_to),
        ];
      }
    }
  }

  /**
   * Base formatter settings data provider.
   *
   * @return array
   *   Nested arrays of values to check:
   *     - $base_settings
   *     - $expected_format_separate
   *     - $expected_format_combined
   */
  protected function fieldFormatterBaseDataProvider() {
    yield [
      [],
      '%s-%s',
      '%s',
    ];
    yield [
      [
        'range_combine' => FALSE,
      ],
      '%s-%s',
      '%s-%s',
    ];
    yield [
      [
        'range_separator' => '|',
      ],
      '%s|%s',
      '%s',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'range_separator' => '=',
      ],
      '%s=%s',
      '%s=%s',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
      ],
      'field_prefix_%s-%s_field_suffix',
      'field_prefix_%s_field_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
      ],
      'from_prefix_%s_from_suffix-%s',
      'from_prefix_%s_from_suffix',
    ];
    yield [
      [
        'to_prefix_suffix' => TRUE,
      ],
      '%s-to_prefix_%s_to_suffix',
      'to_prefix_%s_to_suffix',
    ];
    yield [
      [
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-%s',
      'combined_prefix_%s_combined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-%s',
      '%s-%s',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
      ],
      'field_prefix_from_prefix_%s_from_suffix-%s_field_suffix',
      'field_prefix_from_prefix_%s_from_suffix_field_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
      ],
      'field_prefix_%s-to_prefix_%s_to_suffix_field_suffix',
      'field_prefix_to_prefix_%s_to_suffix_field_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_%s-%s_field_suffix',
      'field_prefix_combined_prefix_%s_combined_suffix_field_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_%s-%s_field_suffix',
      'field_prefix_%s-%s_field_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
      ],
      'from_prefix_%s_from_suffix-to_prefix_%s_to_suffix',
      'from_prefix_%s_to_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix_%s_from_suffix-%s',
      'combined_prefix_%s_combined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix_%s_from_suffix-%s',
      'from_prefix_%s_from_suffix-%s',
    ];
    yield [
      [
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-to_prefix_%s_to_suffix',
      'combined_prefix_%s_combined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-to_prefix_%s_to_suffix',
      '%s-to_prefix_%s_to_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
      ],
      'field_prefix_from_prefix_%s_from_suffix-to_prefix_%s_to_suffix_field_suffix',
      'field_prefix_from_prefix_%s_to_suffix_field_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_from_prefix_%s_from_suffix-%s_field_suffix',
      'field_prefix_combined_prefix_%s_combined_suffix_field_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_from_prefix_%s_from_suffix-%s_field_suffix',
      'field_prefix_from_prefix_%s_from_suffix-%s_field_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_%s-to_prefix_%s_to_suffix_field_suffix',
      'field_prefix_combined_prefix_%s_combined_suffix_field_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_%s-to_prefix_%s_to_suffix_field_suffix',
      'field_prefix_%s-to_prefix_%s_to_suffix_field_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix_%s_from_suffix-to_prefix_%s_to_suffix',
      'combined_prefix_%s_combined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix_%s_from_suffix-to_prefix_%s_to_suffix',
      'from_prefix_%s_from_suffix-to_prefix_%s_to_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_from_prefix_%s_from_suffix-to_prefix_%s_to_suffix_field_suffix',
      'field_prefix_combined_prefix_%s_combined_suffix_field_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix_from_prefix_%s_from_suffix-to_prefix_%s_to_suffix_field_suffix',
      'field_prefix_from_prefix_%s_from_suffix-to_prefix_%s_to_suffix_field_suffix',
    ];
  }

  /**
   * Specific formatter settings data provider.
   *
   * @return array
   *   Nested arrays of values to check:
   *     - $settings
   *     - $from
   *     - $to
   *     - $expected_from
   *     - $expected_to
   */
  abstract protected function fieldFormatterDataProvider();

}

<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\range\Traits\RangeTestTrait;
use Drupal\entity_test\Entity\EntityTest;

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
        $diplay_settings = $settings + $base_settings + $this->defaultSettings;
        $expected_format = $expected_from !== $expected_to ? $expected_format_separate : $expected_format_combined;
        yield [
          $diplay_settings,
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
      'field_prefix%s-%sfield_suffix',
      'field_prefix%sfield_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
      ],
      'from_prefix%sfrom_suffix-%s',
      'from_prefix%sfrom_suffix',
    ];
    yield [
      [
        'to_prefix_suffix' => TRUE,
      ],
      '%s-to_prefix%sto_suffix',
      'to_prefix%sto_suffix',
    ];
    yield [
      [
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-%s',
      'combined_prefix%scombined_suffix',
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
      'field_prefixfrom_prefix%sfrom_suffix-%sfield_suffix',
      'field_prefixfrom_prefix%sfrom_suffixfield_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
      ],
      'field_prefix%s-to_prefix%sto_suffixfield_suffix',
      'field_prefixto_prefix%sto_suffixfield_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix%s-%sfield_suffix',
      'field_prefixcombined_prefix%scombined_suffixfield_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix%s-%sfield_suffix',
      'field_prefix%s-%sfield_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
      ],
      'from_prefix%sfrom_suffix-to_prefix%sto_suffix',
      'from_prefix%sto_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix%sfrom_suffix-%s',
      'combined_prefix%scombined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix%sfrom_suffix-%s',
      'from_prefix%sfrom_suffix-%s',
    ];
    yield [
      [
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-to_prefix%sto_suffix',
      'combined_prefix%scombined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      '%s-to_prefix%sto_suffix',
      '%s-to_prefix%sto_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
      ],
      'field_prefixfrom_prefix%sfrom_suffix-to_prefix%sto_suffixfield_suffix',
      'field_prefixfrom_prefix%sto_suffixfield_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefixfrom_prefix%sfrom_suffix-%sfield_suffix',
      'field_prefixcombined_prefix%scombined_suffixfield_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefixfrom_prefix%sfrom_suffix-%sfield_suffix',
      'field_prefixfrom_prefix%sfrom_suffix-%sfield_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix%s-to_prefix%sto_suffixfield_suffix',
      'field_prefixcombined_prefix%scombined_suffixfield_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefix%s-to_prefix%sto_suffixfield_suffix',
      'field_prefix%s-to_prefix%sto_suffixfield_suffix',
    ];
    yield [
      [
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix%sfrom_suffix-to_prefix%sto_suffix',
      'combined_prefix%scombined_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'from_prefix%sfrom_suffix-to_prefix%sto_suffix',
      'from_prefix%sfrom_suffix-to_prefix%sto_suffix',
    ];
    yield [
      [
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefixfrom_prefix%sfrom_suffix-to_prefix%sto_suffixfield_suffix',
      'field_prefixcombined_prefix%scombined_suffixfield_suffix',
    ];
    yield [
      [
        'range_combine' => FALSE,
        'field_prefix_suffix' => TRUE,
        'from_prefix_suffix' => TRUE,
        'to_prefix_suffix' => TRUE,
        'combined_prefix_suffix' => TRUE,
      ],
      'field_prefixfrom_prefix%sfrom_suffix-to_prefix%sto_suffixfield_suffix',
      'field_prefixfrom_prefix%sfrom_suffix-to_prefix%sto_suffixfield_suffix',
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

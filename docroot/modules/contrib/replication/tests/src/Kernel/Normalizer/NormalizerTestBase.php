<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Workspace;

abstract class NormalizerTestBase extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'serialization',
    'multiversion',
    'key_value',
    'system',
    'field',
    'entity_test',
    'replication',
    'text',
    'filter',
    'user',
    'link',
    'file',
    'language',
    'content_translation',
  ];

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('entity_test_mulrev');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('file');
    $this->installEntitySchema('replication_log');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('system', ['key_value_expire', 'sequences', 'url_alias', 'router']);
    $this->installSchema('key_value', ['key_value_sorted']);
    $this->installConfig(['multiversion', 'replication', 'language', 'field']);
    $this->container->get('multiversion.manager')->enableEntityTypes();
    $this->container->get('router.builder')->rebuild();

    // Auto-create a field for testing.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test_mulrev',
      'field_name' => 'field_test_text',
      'type' => 'text',
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrev',
      'field_name' => 'field_test_text',
      'bundle' => 'entity_test_mulrev',
      'label' => 'Test text-field',
      'widget' => [
        'type' => 'text_textfield',
        'weight' => 0,
      ],
    ])->save();

    $this->serializer = $this->container->get('serializer');
    // Create default workspace.
    Workspace::create(['machine_name' => 'live', 'label' => 'Live', 'type' => 'basic'])->save();
  }

  /**
   * Formats a UNIX timestamp.
   *
   * This is copied from
   * \Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait in Drupal
   * 8.4.x.
   *
   * Depending on the 'bc_timestamp_normalizer_unix' setting. The return will be
   * an RFC3339 date string or the same timestamp that was passed in.
   *
   * @param int $timestamp
   *   The timestamp value to format.
   *
   * @return array
   *   The formatted RFC3339 date string or UNIX timestamp.
   *
   * @see \Drupal\serialization\Normalizer\TimestampItemNormalizer
   */
  protected function formatExpectedTimestampItemValues($timestamp) {
    // Get the minor version only from the \Drupal::VERSION string.
    $minor_version = substr(\Drupal::VERSION, 0, 3);

    // If the setting is enabled, just return the timestamp as-is now.
    if (version_compare($minor_version, '8.4', '<') || $this->config('serialization.settings')->get('bc_timestamp_normalizer_unix')) {
      return ['value' => $timestamp];
    }

    // Otherwise, format the date string to the same that
    // \Drupal\serialization\Normalizer\TimestampItemNormalizer will produce.
    $date = new \DateTime();
    $date->setTimestamp($timestamp);
    $date->setTimezone(new \DateTimeZone('UTC'));

    // Format is also added to the expected return values.
    return [
      'value' => $date->format(\DateTime::RFC3339),
      'format' => \DateTime::RFC3339,
    ];
  }

}

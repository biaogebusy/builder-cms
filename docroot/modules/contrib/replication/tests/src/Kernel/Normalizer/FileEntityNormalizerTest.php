<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\file\Entity\File;

/**
 * Tests the file entity serialization.
 *
 * @group replication
 */
class FileEntityNormalizerTest extends NormalizerTestBase {

  public static $modules = [
    'serialization',
    'system',
    'field',
    'entity_test',
    'text',
    'filter',
    'user',
    'key_value',
    'multiversion',
    'rest',
    'replication',
    'file',
    'image',
  ];

  protected $entityClass = 'Drupal\file\Entity\File';

  /**
   * Tests using entity fields of the file field type.
   */
  public function testNormalizer() {
    // Create two txt files.
    file_put_contents('public://example1.txt', $this->randomMachineName());
    $file = File::create(['uri' => 'public://example1.txt']);
    $file->setOwnerId(1);
    $file->save();

    // Test normalize.
    $uri = $file->getFileUri();
    $file_contents = file_get_contents($uri);
    $expected_attachment = [
      'uuid' => $file->uuid(),
      'uri' => $uri,
      'content_type' => $file->getMimeType(),
      'digest' => 'md5-' . base64_encode(md5($file_contents)),
      'length' => $file->getSize(),
      'data' => base64_encode($file_contents),
    ];

    list($i, $hash) = explode('-', $file->_rev->value);
    $expected = [
      '@context' => [
        '_id' => '@id',
        '@language' => 'en'
      ],
      '@type' => 'file',
      'en' => [
        '@context' => [
          '@language' => 'en',
        ],
        'langcode' => [
          ['value' => 'en'],
        ],
        'uid' => [
          ['target_id' => 1],
        ],
        'filename' => [
          ['value' => $file->getFilename()],
        ],
        'uri' => [
          ['value' => $uri],
        ],
        'filemime' => [
          ['value' => $file->getMimeType()],
        ],
        'filesize' => [
          ['value' => $file->getSize()],
        ],
        'status' => [
          ['value' => FALSE],
        ],
        'created' => [
          $this->formatExpectedTimestampItemValues($file->created->value),
        ],
        'changed' => [
          $this->formatExpectedTimestampItemValues($file->changed->value),
        ],
        '_rev' => [
          ['value' => $file->_rev->value],
        ],
      ],
      '@attachment' => $expected_attachment,
      '_id' => $file->uuid(),
      '_rev' => $file->_rev->value,
      '_revisions' => [
        'start' => 1,
        'ids' => [$hash],
      ],
    ];


    $normalized = $this->serializer->normalize($file);

    // Get the minor version only from the \Drupal::VERSION string.
    $minor_version = substr(\Drupal::VERSION, 0, 3);
    if (version_compare($minor_version, '8.5', '>=')) {
      $expected['en']['revision_default'] = [['value' => TRUE]];
      unset($normalized['en']['uri'][0]['url']);
    }

    foreach (array_keys($expected) as $key) {
      $this->assertEquals($expected[$key], $normalized[$key], "Field $key is normalized correctly.");
    }
    $this->assertEquals(array_diff_key($normalized, $expected), [], 'No unexpected data is added to the normalized array.');

    // Test denormalize.
    $denormalized = $this->serializer->denormalize($normalized, $this->entityClass, 'json');
    $this->assertTrue($denormalized instanceof $this->entityClass, new FormattableMarkup('Denormalized entity is an instance of @class', ['@class' => $this->entityClass]));
    $this->assertSame($denormalized->getEntityTypeId(), $file->getEntityTypeId(), 'Expected entity type found.');
    $this->assertSame($denormalized->bundle(), $file->bundle(), 'Expected entity bundle found.');
    $this->assertSame($denormalized->uuid(), $file->uuid(), 'Expected entity UUID found.');
  }

}

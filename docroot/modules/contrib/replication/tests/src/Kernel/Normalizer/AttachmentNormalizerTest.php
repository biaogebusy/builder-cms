<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\file\Entity\File;

/**
 * Tests the attachment serialization format.
 *
 * @group replication
 */
class AttachmentNormalizerTest extends NormalizerTestBase {

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * @var string
   */
  protected $fileContents;

  /**
   * @var stream
   */
  protected $fileHandle;

  /**
   * @var string
   */
  protected $entityClass = 'Drupal\file\Entity\File';

  /**
   * @var \Drupal\file\Entity\File
   */
  protected $fileEntity;

  protected function setUp() {
    parent::setUp();

    $this->fileContents = $this->randomString();
    $this->fileHandle = fopen('temporary://' . $this->randomMachineName(), 'w+b');
    fwrite($this->fileHandle, $this->fileContents);
    rewind($this->fileHandle);

    $meta = stream_get_meta_data($this->fileHandle);
    $this->fileEntity = File::create(['uri' => $meta['uri']]);
  }

  public function testNormalizer() {
    // Test normalize.
    $normalized = $this->serializer->normalize($this->fileEntity);
    $this->assertTrue(!is_resource($normalized), 'File entity was normalized to a file entity.');

    // Test normalize.
    $normalized = $this->serializer->normalize($this->fileEntity, 'json');
    $this->assertTrue(!is_resource($normalized), 'File entity was normalized to a file entity.');

    // Test normalize.
    $normalized = $this->serializer->normalize($this->fileEntity, 'stream');
    $this->assertTrue(is_resource($normalized), 'File entity was normalized to a file resource.');

    // Test normalize.
    $normalized = $this->serializer->normalize($this->fileEntity, 'base64_stream');
    $this->assertTrue(is_resource($normalized), 'File entity was normalized to a file resource.');

    // Test serialize.
    $serialized = $this->serializer->serialize($this->fileEntity, 'stream');
    $this->assertEquals($serialized, $this->fileContents, 'File entity was serialized to file contents.');

    // Test denormalize.
    $denormalized = $this->serializer->denormalize($this->fileHandle, $this->entityClass, 'stream');
    $this->assertTrue($denormalized instanceof $this->entityClass, new FormattableMarkup('Denormalized entity is an instance of @class', ['@class' => $this->entityClass]));
    $this->assertSame($this->fileEntity->getEntityTypeId(), $denormalized->getEntityTypeId(), 'Expected entity type found.');
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\ImageClassification;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationItem;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationOutput;

/**
 * This tests the Image classification calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\ImageClassification\ImageClassificationInterface
 *
 * @group ai
 */
class ImageClassificationInterfaceTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * Model for the setup.
   *
   * @var string
   */
  protected $model;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * Test the image classification.
   */
  public function testImageClassificationNormal(): void {
    $binary = 'testsetestt';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ImageClassificationInput(new ImageFile($binary, 'image/jpeg', 'test.jpg'));
    $input->setLabels([
      'normal',
      'nsfw',
    ]);
    $classification = $provider->imageClassification($input, 'test');
    // Should be a ImageClassificationOutput object.
    $this->assertInstanceOf(ImageClassificationOutput::class, $classification);

    $normalized = $classification->getNormalized();
    // Normalized output should be an array.
    $this->assertIsArray($normalized);
    // The array should have 2 elements.
    $this->assertCount(2, $normalized);
    // The first object should be an ImageClassificationItem object.
    $this->assertInstanceOf(ImageClassificationItem::class, $normalized[0]);
    // The first object label should be normal.
    $this->assertEquals('normal', $normalized[0]->getLabel());
    // The first object confidence should be 0.5.
    $this->assertEquals(0.5, $normalized[0]->getConfidenceScore());
    // The second object should be an ImageClassificationItem object.
    $this->assertInstanceOf(ImageClassificationItem::class, $normalized[1]);
    // The second object label should be nsfw.
    $this->assertEquals('nsfw', $normalized[1]->getLabel());
    // The second object confidence should be 0.5.
    $this->assertEquals(0.5, $normalized[1]->getConfidenceScore());
  }

  /**
   * Test the image classification without a binary.
   */
  public function testImageClassificationBroken(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ImageClassificationInput(new ImageFile('', 'image/jpeg', 'test.jpg'));
    $this->expectException(AiBadRequestException::class);
    $provider->imageClassification($input, $this->model);
  }

}

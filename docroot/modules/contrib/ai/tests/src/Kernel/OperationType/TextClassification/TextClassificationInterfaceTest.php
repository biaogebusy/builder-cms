<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\TextClassification;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use Drupal\ai\OperationType\TextClassification\TextClassificationItem;
use Drupal\ai\OperationType\TextClassification\TextClassificationOutput;

/**
 * This tests the Text Classification calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\TextClassification\TextClassificationInterface
 *
 * @group ai
 */
class TextClassificationInterfaceTest extends KernelTestBase {

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
   * Test the text classification.
   */
  public function testTextClassificationNormal(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new TextClassificationInput('This product is great!');
    $input->setLabels([
      'positive',
      'negative',
      'neutral',
    ]);
    $classification = $provider->textClassification($input, 'test');
    // Should be a TextClassificationOutput object.
    $this->assertInstanceOf(TextClassificationOutput::class, $classification);

    $normalized = $classification->getNormalized();
    // Normalized output should be an array.
    $this->assertIsArray($normalized);
    // The array should have 3 elements.
    $this->assertCount(3, $normalized);
    // The first object should be a TextClassificationItem object.
    $this->assertInstanceOf(TextClassificationItem::class, $normalized[0]);
    // The first object label should be positive.
    $this->assertEquals('positive', $normalized[0]->getLabel());
    // The first object confidence should be 0.5.
    $this->assertEquals(0.5, $normalized[0]->getConfidenceScore());
    // The second object should be a TextClassificationItem object.
    $this->assertInstanceOf(TextClassificationItem::class, $normalized[1]);
    // The second object label should be negative.
    $this->assertEquals('negative', $normalized[1]->getLabel());
    // The third object label should be neutral.
    $this->assertEquals('neutral', $normalized[2]->getLabel());
  }

  /**
   * Test the text classification without a model.
   */
  public function testTextClassificationBroken(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new TextClassificationInput('This product is great!');
    $this->expectException(AiBadRequestException::class);
    $provider->textClassification($input, $this->model);
  }

}

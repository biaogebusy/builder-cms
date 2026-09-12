<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageClassification;

use Drupal\ai\OperationType\ImageClassification\ImageClassificationItem;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ImageClassificationItem class.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageClassification\ImageClassificationItem
 */
class ImageClassificationItemTest extends TestCase {

  /**
   * Test getting and setting the label.
   */
  public function testLabel(): void {
    $item = new ImageClassificationItem('cat', 0.95);
    $this->assertEquals('cat', $item->getLabel());

    $item->setLabel('dog');
    $this->assertEquals('dog', $item->getLabel());
  }

  /**
   * Test getting and setting the confidence score.
   */
  public function testConfidenceScore(): void {
    $item = new ImageClassificationItem('cat', 0.95);
    $this->assertEquals(0.95, $item->getConfidenceScore());

    $item->setConfidenceScore(0.8);
    $this->assertEquals(0.8, $item->getConfidenceScore());

    $item->setConfidenceScore(NULL);
    $this->assertNull($item->getConfidenceScore());
  }

  /**
   * Test that the default confidence score is NULL.
   */
  public function testDefaultConfidenceScore(): void {
    $item = new ImageClassificationItem('cat');
    $this->assertNull($item->getConfidenceScore());
  }

  /**
   * Test getConfidenceScorePercentage returns a float.
   */
  public function testGetConfidenceScorePercentage(): void {
    $item = new ImageClassificationItem('cat', 0.956);
    $result = $item->getConfidenceScorePercentage();

    $this->assertIsFloat($result);
    $this->assertEquals(95.6, $result);
  }

  /**
   * Test getConfidenceScorePercentage returns 0.0 when score is null.
   */
  public function testGetConfidenceScorePercentageNull(): void {
    $item = new ImageClassificationItem('cat');
    $result = $item->getConfidenceScorePercentage();

    $this->assertIsFloat($result);
    $this->assertEquals(0.0, $result);
  }

  /**
   * Test getConfidenceScorePercentage returns 0.0 when score is zero.
   */
  public function testGetConfidenceScorePercentageZero(): void {
    $item = new ImageClassificationItem('cat', 0.0);
    $result = $item->getConfidenceScorePercentage();

    $this->assertIsFloat($result);
    $this->assertEquals(0.0, $result);
  }

  /**
   * Test getConfidenceScorePercentage with full confidence.
   */
  public function testGetConfidenceScorePercentageFull(): void {
    $item = new ImageClassificationItem('cat', 1.0);
    $result = $item->getConfidenceScorePercentage();

    $this->assertIsFloat($result);
    $this->assertEquals(100.0, $result);
  }

}

<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextClassification;

use Drupal\ai\OperationType\TextClassification\TextClassificationItem;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the item functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextClassification\TextClassificationItem
 */
class TextClassificationItemTest extends TestCase {

  /**
   * Test getting and setting for the item.
   */
  public function testGetSet(): void {
    $item = $this->getItem();
    $this->assertEquals('positive', $item->getLabel());
    $this->assertEquals(0.95, $item->getConfidenceScore());

    $item->setLabel('negative');
    $this->assertEquals('negative', $item->getLabel());

    $item->setConfidenceScore(0.1);
    $this->assertEquals(0.1, $item->getConfidenceScore());
  }

  /**
   * Test the confidence score percentage.
   */
  public function testConfidenceScorePercentage(): void {
    $item = $this->getItem();
    $this->assertEquals('95', $item->getConfidenceScorePercentage());
  }

  /**
   * Test null confidence score.
   */
  public function testNullConfidenceScore(): void {
    $item = new TextClassificationItem('unknown');
    $this->assertNull($item->getConfidenceScore());
    $this->assertEquals('0', $item->getConfidenceScorePercentage());
  }

  /**
   * Helper function to get the item.
   *
   * @return \Drupal\ai\OperationType\TextClassification\TextClassificationItem
   *   The item.
   */
  public function getItem(): TextClassificationItem {
    return new TextClassificationItem('positive', 0.95);
  }

}

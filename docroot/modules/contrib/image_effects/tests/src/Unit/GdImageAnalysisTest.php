<?php

namespace Drupal\Tests\image_effects\Unit;

use Drupal\image_effects\Component\GdImageAnalysis;
use PHPUnit\Framework\TestCase;

/**
 * Tests the image analysis helper methods.
 *
 * @group image_effects
 */
class GdImageAnalysisTest extends TestCase {

  /**
   * Test image.
   *
   * @var resource
   */
  protected $testImage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a test image with 3 red, 3 green, and 3 blue pixels.
    $this->testImage = imagecreatetruecolor(3, 3);
    imagefilledrectangle($this->testImage, 0, 0, 2, 0, imagecolorallocate($this->testImage, 255, 0, 0));
    imagefilledrectangle($this->testImage, 0, 1, 2, 1, imagecolorallocate($this->testImage, 0, 255, 0));
    imagefilledrectangle($this->testImage, 0, 2, 2, 2, imagecolorallocate($this->testImage, 0, 0, 255));
  }

  /**
   * Verify the mean calculation with a known image.
   */
  public function testMean() {
    $this->assertEquals(85, GdImageAnalysis::mean($this->testImage));
  }

  /**
   * Verify the difference calculation.
   */
  public function testDifference() {
    $image1 = imagecreatefrompng(__DIR__ . '/../../images/left.png');
    $image2 = imagecreatefrompng(__DIR__ . '/../../images/left.png');
    $diff = GdImageAnalysis::difference($image1, $image2);
    $this->assertEquals(0, GdImageAnalysis::mean($diff));
    $this->assertEquals([
      0 => 120,
      256 => 120,
      512 => 120,
    ], array_filter(GdImageAnalysis::histogram($diff)));
    $this->assertEquals(1.585, round(GdImageAnalysis::entropy($diff), 3));
  }

  /**
   * Verify the histogram calculation with a known image.
   */
  public function testHistogram() {
    $expected_histogram = [
      0 => 6,
      255 => 3,
      256 => 6,
      511 => 3,
      512 => 6,
      767 => 3,
    ];
    $this->assertEquals($expected_histogram, array_filter(GdImageAnalysis::histogram($this->testImage)));
  }

  /**
   * Verify the entropy calculation with a known image.
   */
  public function testEntropy() {
    // Calculate the expected values.
    // There are 9 bins in the histogram, 3 colors * 3 channels.
    $expected_entroy = (1 / 3 * log(1 / 9, 2) + 2 / 3 * log(2 / 9, 2)) * -1;
    $this->assertTrue(GdImageAnalysis::entropy($this->testImage) - $expected_entroy < 0.001);
  }

}

<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Invert effect test.
 *
 * @group image_effects
 */
class InvertTest extends ImageEffectsTestBase {

  /**
   * Invert effect test.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array $toolkit_settings
   *   The settings of the toolkit to set up.
   *
   * @dataProvider providerToolkits
   */
  public function testInvertEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Add Invert effect to the test image style.
    $effect = [
      'id' => 'image_effects_invert',
    ];
    $this->addEffectToTestStyle($effect);

    // Test on the PNG test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    // Expected colors after negate.
    $colors = [
      // Red is converted to cyan.
      $this->cyan,
      // Green is converted to fuchsia.
      $this->fuchsia,
      // Transparent remains transparent.
      $this->transparent,
      // Blue is converted to yellow.
      $this->yellow,
    ];

    // Check that ::applyEffect generates image with inverted colors.
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($colors[0], $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($colors[1], $this->getPixelColor($image, 39, 0));
    $this->assertColorsAreEqual($colors[2], $this->getPixelColor($image, 0, 19));
    $this->assertColorsAreEqual($colors[3], $this->getPixelColor($image, 39, 19));
  }

}

<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Brightness effect test.
 *
 * @group image_effects
 */
class BrightnessTest extends ImageEffectsTestBase {

  /**
   * {@inheritdoc}
   */
  public function providerToolkits() {
    $toolkits = parent::providerToolkits();
    // @todo This effect does not work on GraphicsMagick.
    unset($toolkits['ImageMagick-graphicsmagick']);
    return $toolkits;
  }

  /**
   * Brightness effect test.
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
  public function testBrightnessEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Test on the PNG test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    // Test data.
    $test_data = [
      // No brightness change.
      '0' => [$this->red, $this->green, $this->transparent, $this->blue],
      // Adjust brightness by -100%.
      '-100' => [$this->black, $this->black, $this->transparent, $this->black],
      // Adjust brightness by 100%.
      '100' => [$this->white, $this->white, $this->transparent, $this->white],
    ];

    foreach ($test_data as $key => $colors) {
      // Add Brightness effect to the test image style.
      $effect = [
        'id' => 'image_effects_brightness',
        'data' => [
          'level' => $key,
        ],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::applyEffect generates image with expected brightness.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertColorsAreEqual($colors[0], $this->getPixelColor($image, 0, 0));
      $this->assertColorsAreEqual($colors[1], $this->getPixelColor($image, 39, 0));
      $this->assertColorsAreEqual($colors[2], $this->getPixelColor($image, 0, 19));
      $this->assertColorsAreEqual($colors[3], $this->getPixelColor($image, 39, 19));

      // Remove effect.
      $uuid = $this->removeEffectFromTestStyle($uuid);
    }
  }

}

<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Color Shift effect test.
 *
 * @group image_effects
 */
class ColorShiftTest extends ImageEffectsTestBase {

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
   * Color Shift effect test.
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
  public function testColorShiftEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Test on the PNG test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    // Test data.
    $test_data = [
      // Shift to red.
      '#FF0000' => [
        $this->red,
        $this->yellow,
        $this->transparent,
        $this->fuchsia,
      ],
      // Shift to green.
      '#00FF00' => [
        $this->yellow,
        $this->green,
        $this->transparent,
        $this->cyan,
      ],
      // Shift to blue.
      '#0000FF' => [
        $this->fuchsia,
        $this->cyan,
        $this->transparent,
        $this->blue,
      ],
      // Arbitrary shift.
      '#929BEF'  => [
        [255, 155, 239, 0],
        [146, 255, 239, 0],
        $this->transparent,
        [146, 155, 255, 0],
      ],
    ];

    foreach ($test_data as $key => $colors) {
      // Add Color Shift effect to the test image style.
      $effect = [
        'id' => 'image_effects_color_shift',
        'data' => [
          'RGB][hex' => $key,
        ],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::applyEffect generates image with expected color shift.
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

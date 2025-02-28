<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Opacity effect test.
 *
 * @group image_effects
 */
class OpacityTest extends ImageEffectsTestBase {

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
   * Opacity effect test.
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
  public function testOpacityEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Test on the PNG test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    // Test data.
    $test_data = [
      // No transparency change.
      '100' => [
        $this->red,
        $this->green,
        $this->transparent,
        $this->blue,
      ],
      // 50% transparency.
      '50' => [
        [255, 0, 0, 63],
        [0, 255, 0, 63],
        $this->transparent,
        [0, 0, 255, 63],
      ],
      // 100% transparency.
      '0' => [
        $this->transparent,
        $this->transparent,
        $this->transparent,
        $this->transparent,
      ],
    ];

    foreach ($test_data as $opacity => $colors) {
      // Add Opacity effect to the test image style.
      $effect = [
        'id' => 'image_effects_opacity',
        'data' => [
          'opacity' => $opacity,
        ],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::applyEffect generates image with expected opacity.
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

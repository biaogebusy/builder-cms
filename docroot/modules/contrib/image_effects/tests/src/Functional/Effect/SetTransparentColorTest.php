<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Set transparent color effect test.
 *
 * @group image_effects
 */
class SetTransparentColorTest extends ImageEffectsTestBase {

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
   * Set transparent color effect test.
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
  public function testSetTransparentColorEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Test on the GIF test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.gif');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    // Test data.
    $test_data = [
      '#FF0000' => [
        $this->transparent,
        $this->green,
        $this->yellow,
        $this->blue,
      ],
      '#00FF00' => [
        $this->red,
        $this->transparent,
        $this->yellow,
        $this->blue,
      ],
      '#0000FF' => [
        $this->red,
        $this->green,
        $this->yellow,
        $this->transparent,
      ],
      ''  => [
        $this->red,
        $this->green,
        $this->transparent,
        $this->blue,
      ],
    ];

    foreach ($test_data as $key => $colors) {
      // Add Set transparent color effect to the test image style.
      $effect = [
        'id' => 'image_effects_set_transparent_color',
        'data' => [
          'transparent_color][container][transparent' => empty($key) ? TRUE : FALSE,
          'transparent_color][container][hex' => $key,
        ],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::applyEffect generates image with expected transparent
      // color. GD slightly compresses GIF colors so we use the
      // ::assertColorsAreClose method for testing.
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertColorsAreClose($colors[0], $this->getPixelColor($image, 0, 0), 40);
      $this->assertColorsAreClose($colors[1], $this->getPixelColor($image, 39, 0), 40);
      $this->assertColorsAreClose($colors[2], $this->getPixelColor($image, 0, 19), 40);
      $this->assertColorsAreClose($colors[3], $this->getPixelColor($image, 39, 19), 40);

      // Remove effect.
      $uuid = $this->removeEffectFromTestStyle($uuid);
    }
  }

}

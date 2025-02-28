<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Watermark effect test.
 *
 * @group image_effects
 */
class WatermarkTest extends ImageEffectsTestBase {

  /**
   * Watermark effect test.
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
  public function testWatermarkEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // 1. Basic test.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-1.png');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $watermark_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    $effect = [
      'id' => 'image_effects_watermark',
      'data' => [
        'watermark_image' => $watermark_uri,
        'placement' => 'left-top',
        'x_offset][c0][c1][value' => 1,
        'x_offset][c0][c1][uom' => 'px',
        'y_offset][c0][c1][value' => 1,
        'y_offset][c0][c1][uom' => 'px',
        'opacity' => 100,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::applyEffect generates image with expected watermark.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $watermark = $this->imageFactory->get($watermark_uri, 'gd');
    $this->assertColorsAreNotEqual($this->getPixelColor($watermark, 0, 0), $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->getPixelColor($watermark, 0, 0), $this->getPixelColor($image, 1, 1));
    $this->assertColorsAreEqual($this->getPixelColor($watermark, 0, 1), $this->getPixelColor($image, 1, 2));
    $this->assertColorsAreEqual($this->getPixelColor($watermark, 0, 3), $this->getPixelColor($image, 1, 4));

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);

    // 2. Test for scaled watermark. Place a fuchsia watermark scaled to 5%
    // over a sample image and check the color of pixels inside/outside the
    // watermark to see that it was scaled properly.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-1.png');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $watermark_uri = $this->getTestImageCopyUri('/tests/images/fuchsia.png', 'image_effects');

    $effect = [
      'id' => 'image_effects_watermark',
      'data' => [
        'watermark_image' => $watermark_uri,
        'placement' => 'left-top',
        'x_offset][c0][c1][value' => NULL,
        'y_offset][c0][c1][value' => NULL,
        'opacity' => 100,
        'watermark_resize][watermark_width][c0][c1][value' => 5,
        'watermark_resize][watermark_width][c0][c1][uom' => 'perc',
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::applyEffect generates image with expected watermark.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    // GD slightly compresses fuchsia while resampling, so checking color
    // in and out the watermark needs a tolerance.
    $this->assertColorsAreClose($this->getPixelColor($image, 17, 0), $this->fuchsia, 4);
    $this->assertColorsAreNotClose($this->getPixelColor($image, 19, 0), $this->fuchsia, 4);
    $this->assertColorsAreClose($this->getPixelColor($image, 0, 13), $this->fuchsia, 4);
    $this->assertColorsAreNotClose($this->getPixelColor($image, 0, 15), $this->fuchsia, 4);

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);

    // 3. Test for watermark PNG image with full transparency set, 100% opacity
    // watermark.
    $original_uri = $this->getTestImageCopyUri('/tests/images/fuchsia.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $watermark_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    $effect = [
      'id' => 'image_effects_watermark',
      'data' => [
        'watermark_image' => $watermark_uri,
        'placement' => 'left-top',
        'x_offset][c0][c1][value' => NULL,
        'y_offset][c0][c1][value' => NULL,
        'opacity' => 100,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::applyEffect generates image with expected transparency.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->getPixelColor($image, 0, 19), $this->fuchsia);

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);

    // 4. Test for watermark PNG image with full transparency set, 50% opacity
    // watermark.
    // -----------------------------------------------------------------------
    // Skip on ImageMagick toolkit with GraphicsMagick package selected.
    // @todo see if GraphicsMagick can support opacity setting.
    if ($this->imageFactory->getToolkitId() === 'imagemagick' && \Drupal::configFactory()->get('imagemagick.settings')->get('binaries') === 'graphicsmagick') {
      return;
    }

    $original_uri = $this->getTestImageCopyUri('/tests/images/fuchsia.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $watermark_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    $effect = [
      'id' => 'image_effects_watermark',
      'data' => [
        'watermark_image' => $watermark_uri,
        'placement' => 'left-top',
        'x_offset][c0][c1][value' => NULL,
        'y_offset][c0][c1][value' => NULL,
        'opacity' => 50,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::applyEffect generates image with expected alpha.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->getPixelColor($image, 0, 19), $this->fuchsia);
    // GD and ImageMagick return slightly different colors, use the
    // ::assertColorsAreClose method.
    $this->assertColorsAreClose($this->getPixelColor($image, 39, 0), $this->grey, 4);

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);
  }

}

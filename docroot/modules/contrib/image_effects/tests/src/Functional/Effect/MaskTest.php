<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Mask effect test.
 *
 * @group image_effects
 */
class MaskTest extends ImageEffectsTestBase {

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
   * Mask effect test.
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
  public function testMaskEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // 1. Basic test. Apply the mask to a full fuchsia image, without resizing.
    $original_uri = $this->getTestImageCopyUri('/tests/images/fuchsia.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $mask_uri = $this->getTestImageCopyUri('/tests/images/image-mask.png', 'image_effects');

    $effect = [
      'id' => 'image_effects_mask',
      'data' => [
        'mask_image' => $mask_uri,
        'placement' => 'left-top',
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::applyEffect generates image with expected mask.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 39, 0));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 0, 19));
    $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($image, 39, 19));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 79, 0));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 0, 59));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 79, 59));

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);

    // 2. Test for scaled mask. Place the mask scaled to 100%.
    $effect = [
      'id' => 'image_effects_mask',
      'data' => [
        'mask_image' => $mask_uri,
        'mask_resize][mask_width][c0][c1][value' => 100,
        'mask_resize][mask_width][c0][c1][uom' => 'perc',
        'mask_resize][mask_height][c0][c1][value' => 100,
        'mask_resize][mask_height][c0][c1][uom' => 'perc',
        'placement' => 'left-top',
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::applyEffect generates image with expected mask.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 79, 0));
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 0, 59));
    $this->assertColorsAreEqual($this->fuchsia, $this->getPixelColor($image, 79, 59));

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);
  }

}

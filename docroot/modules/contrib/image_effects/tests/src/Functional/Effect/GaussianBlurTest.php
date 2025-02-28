<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Gaussian blur effect test.
 *
 * @group image_effects
 */
class GaussianBlurTest extends ImageEffectsTestBase {

  /**
   * Gaussian blur effect test.
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
  public function testGaussianBlurEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $effect = [
      'id' => 'image_effects_gaussian_blur',
      'data' => [
        'radius' => 3,
        'sigma' => 2,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    // 1. Test blurring red on green.
    $original_uri = $this->getTestImageCopyUri('/tests/images/red-on-green.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);
    // Check that ::applyEffect generates image with expected blur.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->green, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->red, $this->getPixelColor($image, 50, 50));
    // The upper-left corner of the inner red square has been blurred.
    // For fully opaque, we check an actual color.
    $this->assertColorsAreClose([94, 161, 0, 0], $this->getPixelColor($image, 25, 25), 5);

    // 2. Test blurring red on transparent.
    $original_uri = $this->getTestImageCopyUri('/tests/images/red-on-transparent.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);
    // Check that ::applyEffect generates image with expected blur.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->transparent, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->red, $this->getPixelColor($image, 50, 50));
    // The upper-left corner of the inner red square has been blurred.
    // For fully transparent, the background color differs by toolkit. In this
    // case, we just check for the alpha channel value equal to 80.
    $this->assertEquals(80, imagecolorsforindex($image->getToolkit()->getResource(), imagecolorat($image->getToolkit()->getResource(), 25, 25))['alpha']);
  }

}

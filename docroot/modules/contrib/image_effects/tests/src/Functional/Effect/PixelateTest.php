<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Pixelate effect test.
 *
 * @group image_effects
 */
class PixelateTest extends ImageEffectsTestBase {

  /**
   * Pixelate effect test.
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
  public function testPixelateEffects($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $effect = [
      'id' => 'image_effects_pixelate',
      'data' => [
        'size' => 20,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    $original_uri = $this->getTestImageCopyUri('/tests/images/red-on-green.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);
    // Check that ::applyEffect generates image with expected pixelation.
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertColorsAreEqual($this->green, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->red, $this->getPixelColor($image, 50, 50));

    $this->assertColorsAreClose([143, 111, 0, 0], $this->getPixelColor($image, 30, 30), 1);
    $this->assertColorsAreClose([143, 111, 0, 0], $this->getPixelColor($image, 70, 30), 1);
    $this->assertColorsAreClose([143, 111, 0, 0], $this->getPixelColor($image, 70, 70), 1);
    $this->assertColorsAreClose([143, 111, 0, 0], $this->getPixelColor($image, 30, 70), 1);

    $this->assertColorsAreClose([191, 63, 0, 0], $this->getPixelColor($image, 50, 30), 1);
    $this->assertColorsAreClose([191, 63, 0, 0], $this->getPixelColor($image, 70, 50), 1);
    $this->assertColorsAreClose([191, 63, 0, 0], $this->getPixelColor($image, 50, 70), 1);
    $this->assertColorsAreClose([191, 63, 0, 0], $this->getPixelColor($image, 30, 50), 1);
  }

}

<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Contrast effect test.
 *
 * @group image_effects
 */
class ContrastTest extends ImageEffectsTestBase {

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
   * Contrast effect test.
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
  public function testContrastEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Test on the PNG test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    // Test data.
    $test_data = [
      // No contrast change.
      '0' => [
        'colors' => [
          $this->red,
          $this->green,
          $this->transparent,
          $this->blue,
        ],
        'tolerance' => 0,
      ],

      // Adjust contrast by -50%.
      // ImageMagick color in test data, GD returns significantly different
      // color.
      '-50' => [
        'colors' => [
          [180, 75, 75, 0],
          [75, 180, 75, 0],
          $this->transparent,
          [75, 75, 180, 0],
        ],
        'tolerance' => 2000,
      ],

      // Adjust contrast by -100%.
      // GD and ImageMagick return slightly different grey.
      '-100' => [
        'colors' => [
          $this->grey,
          $this->grey,
          $this->transparent,
          $this->grey,
        ],
        'tolerance' => 16000,
      ],

      // Adjust contrast by 50%.
      '50' => [
        'colors' => [
          $this->red,
          $this->green,
          $this->transparent,
          $this->blue,
        ],
        'tolerance' => 0,
      ],

      // Adjust contrast by 100%.
      '100' => [
        'colors' => [
          $this->red,
          $this->green,
          $this->transparent,
          $this->blue,
        ],
        'tolerance' => 0,
      ],
    ];

    foreach ($test_data as $key => $entry) {
      // Add contrast effect to the test image style.
      $effect = [
        'id' => 'image_effects_contrast',
        'data' => [
          'level' => $key,
        ],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::applyEffect generates image with expected contrast.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertColorsAreClose($entry['colors'][0], $this->getPixelColor($image, 0, 0), $entry['tolerance']);
      $this->assertColorsAreClose($entry['colors'][1], $this->getPixelColor($image, 39, 0), $entry['tolerance']);
      $this->assertColorsAreClose($entry['colors'][2], $this->getPixelColor($image, 0, 19), $entry['tolerance']);
      $this->assertColorsAreClose($entry['colors'][3], $this->getPixelColor($image, 39, 19), $entry['tolerance']);

      // Remove effect.
      $uuid = $this->removeEffectFromTestStyle($uuid);
    }
  }

}

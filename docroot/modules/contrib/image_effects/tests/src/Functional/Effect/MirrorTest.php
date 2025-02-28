<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Mirror effect test.
 *
 * @group image_effects
 */
class MirrorTest extends ImageEffectsTestBase {

  /**
   * Mirror effect test.
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
  public function testMirrorEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Test on the PNG test image.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');

    // Test data.
    $test_data = [
      // Horizontal mirror.
      'horizontal' => [
        'effect' => [
          'x_axis' => TRUE,
          'y_axis' => FALSE,
        ],
        'expected_text' => 'Mirror horizontal',
        'expected_colors' => [
          $this->green,
          $this->red,
          $this->blue,
          $this->transparent,
        ],
      ],
      // Vertical mirror.
      'vertical' => [
        'effect' => [
          'x_axis' => FALSE,
          'y_axis' => TRUE,
        ],
        'expected_text' => 'Mirror vertical',
        'expected_colors' => [
          $this->transparent,
          $this->blue,
          $this->red,
          $this->green,
        ],
      ],
      // Both horizontal and vertical mirror.
      'both' => [
        'effect' => [
          'x_axis' => TRUE,
          'y_axis' => TRUE,
        ],
        'expected_text' => 'Mirror both horizontal and vertical',
        'expected_colors' => [
          $this->blue,
          $this->transparent,
          $this->green,
          $this->red,
        ],
      ],
    ];

    foreach ($test_data as $data) {
      // Add Mirror effect to the test image style.
      $effect = [
        'id' => 'image_effects_mirror',
        'data' => $data['effect'],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Assert effect summary text.
      $this->assertSession()->pageTextContains($data['expected_text']);

      // Check that ::applyEffect generates image with expected mirror. Colors
      // of the derivative image should be swapped according to the mirror
      // direction.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertColorsAreEqual($data['expected_colors'][0], $this->getPixelColor($image, 0, 0));
      $this->assertColorsAreEqual($data['expected_colors'][1], $this->getPixelColor($image, 39, 0));
      $this->assertColorsAreEqual($data['expected_colors'][2], $this->getPixelColor($image, 0, 19));
      $this->assertColorsAreEqual($data['expected_colors'][3], $this->getPixelColor($image, 39, 19));

      // Remove effect.
      $uuid = $this->removeEffectFromTestStyle($uuid);
    }
  }

}

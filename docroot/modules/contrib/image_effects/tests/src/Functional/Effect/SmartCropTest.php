<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Smart Crop effect test.
 *
 * @group image_effects
 */
class SmartCropTest extends ImageEffectsTestBase {

  /**
   * Test the image_effects_smart_crop effect.
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
  public function testSmartCrop($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $original_uri = $this->getTestImageCopyUri('/tests/images/quarter.png', 'image_effects');
    $expected_image_uri = $this->getTestImageCopyUri('/tests/images/center.png', 'image_effects');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $test_data = [
      '10x10 slice' => [
        'effect_data' => [
          'width][c0][c1][value' => 10,
          'width][c0][c1][uom' => 'px',
          'height][c0][c1][value' => 10,
          'height][c0][c1][uom' => 'px',
          'advanced][algorithm' => 'entropy_slice',
        ],
        'expected_width' => 10,
        'expected_height' => 10,
      ],
      '10x10 grid' => [
        'effect_data' => [
          'width][c0][c1][value' => 10,
          'width][c0][c1][uom' => 'px',
          'height][c0][c1][value' => 10,
          'height][c0][c1][uom' => 'px',
          'advanced][algorithm' => 'entropy_grid',
        ],
        'expected_width' => 10,
        'expected_height' => 10,
      ],
    ];

    foreach ($test_data as $test) {
      // Add effect to the test image style.
      $effect = [
        'id' => 'image_effects_smart_crop',
        'data' => $test['effect_data'],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::transformDimensions returns expected dimensions.
      $image = $this->imageFactory->get($original_uri);
      $this->assertEquals(40, $image->getWidth());
      $this->assertEquals(40, $image->getHeight());
      $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_uri));
      $variables = [
        '#theme' => 'image_style',
        '#style_name' => 'image_effects_test',
        '#uri' => $original_uri,
        '#width' => $image->getWidth(),
        '#height' => $image->getHeight(),
      ];
      $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"{$test['expected_width']}\" height=\"{$test['expected_height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables));

      // Check that ::applyEffect generates image with expected dimensions.
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $actual_image = $this->imageFactory->get($derivative_uri, 'gd');
      $expected_image = $this->imageFactory->get($expected_image_uri, 'gd');
      $this->assertImagesAreEqual($expected_image, $actual_image);

      // Remove effect.
      $uuid = $this->removeEffectFromTestStyle($uuid);
    }
  }

}

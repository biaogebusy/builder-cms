<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Component\Utility\NestedArray;
use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Tests the functionality provided by the relative crop image effect.
 *
 * @group image_effects
 */
class RelativeCropTest extends ImageEffectsTestBase {

  /**
   * Tests that the relative crop effect is applied properly.
   *
   * @param string $toolkit_id
   *   The ID of the toolkit to set up.
   * @param string $toolkit_config
   *   The configuration object of the toolkit to set up.
   * @param array $toolkit_settings
   *   The settings of the toolkit to set up.
   *
   * @dataProvider providerToolkits
   */
  public function testRelativeCrop($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $test_files = [
      'border',
      'border-flat',
      'border-no-bottom',
      'border-no-left',
      'border-no-right',
      'border-no-top',
      'border-thin',
    ];
    foreach ($test_files as $filename) {
      $test_file_uris[$filename] = $this->getTestImageCopyUri("/tests/images/$filename.png", 'image_effects');
      $test_images[$filename] = $this->imageFactory->get($test_file_uris[$filename], 'gd');
    }

    // The 'border' test file serves as the original image in this test.
    $image = $this->imageFactory->get($test_file_uris['border']);
    $this->assertEquals(14, $image->getWidth());
    $this->assertEquals(14, $image->getHeight());
    $image_gd = $this->imageFactory->get($test_file_uris['border'], 'gd');
    $this->assertEquals(14, $image_gd->getWidth());
    $this->assertEquals(14, $image_gd->getHeight());

    $derivative_uri = $this->testImageStyle->buildUri($test_file_uris['border']);
    $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($test_file_uris['border']));

    // Test that no cropping will be done if the ratio matches the original.
    $test_data['Matching ratio'] = [
      'effect_data' => [
        'width' => 1,
        'height' => 1,
      ],
      'expected_image' => 'border',
      'expected_width' => 14,
      'expected_height' => 14,
    ];

    // Test that the image will be cropped if it is too wide.
    $test_data['Crop width center'] = [
      'effect_data' => [
        'width' => 6,
        'height' => 7,
      ],
      'expected_image' => 'border-thin',
      'expected_width' => 12,
      'expected_height' => 14,
    ];
    $test_data['Crop width left'] = NestedArray::mergeDeep($test_data['Crop width center'], [
      'effect_data' => [
        'anchor][width' => 'left',
      ],
      'expected_image' => 'border-no-right',
    ]);
    $test_data['Crop width right'] = NestedArray::mergeDeep($test_data['Crop width center'], [
      'effect_data' => [
        'anchor][width' => 'right',
      ],
      'expected_image' => 'border-no-left',
    ]);
    $test_data['Crop width rounding up'] = NestedArray::mergeDeep($test_data['Crop width center'], [
      'effect_data' => [
        // The width will be rounded to 24 (24:28 = 12:14).
        'width' => 23,
        'height' => 28,
      ],
    ]);
    $test_data['Crop width rounding down'] = NestedArray::mergeDeep($test_data['Crop width center'], [
      'effect_data' => [
        // The width will be rounded to 48 (48:56 = 12:14).
        'width' => 49,
        'height' => 56,
      ],
    ]);

    // Test that the image will be cropped if it is too high.
    $test_data['Crop height center'] = [
      'effect_data' => [
        'width' => 7,
        'height' => 6,
      ],
      'expected_image' => 'border-flat',
      'expected_width' => 14,
      'expected_height' => 12,
    ];
    $test_data['Crop height top'] = NestedArray::mergeDeep($test_data['Crop height center'], [
      'effect_data' => [
        'anchor][height' => 'top',
      ],
      'expected_image' => 'border-no-bottom',
    ]);
    $test_data['Crop height bottom'] = NestedArray::mergeDeep($test_data['Crop height center'], [
      'effect_data' => [
        'anchor][height' => 'bottom',
      ],
      'expected_image' => 'border-no-top',
    ]);
    $test_data['Crop height rounding up'] = NestedArray::mergeDeep($test_data['Crop height center'], [
      'effect_data' => [
        'width' => 28,
        // The height will be rounded to 24 (28:24 = 14:12).
        'height' => 23,
      ],
    ]);
    $test_data['Crop height rounding down'] = NestedArray::mergeDeep($test_data['Crop height center'], [
      'effect_data' => [
        'width' => 56,
        // The height will be rounded to 48 (56:48 = 14:12).
        'height' => 49,
      ],
    ]);

    foreach ($test_data as $test_description => $test) {
      // Add the "Relative crop" effect to the test image style.
      $effect = [
        'id' => 'image_effects_relative_crop',
        'data' => $test['effect_data'],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Assert that the effect summary text is displayed correctly.
      $summary = $test['effect_data']['width'] . ':' . $test['effect_data']['height'];
      $this->assertSession()->pageTextContains('Relative crop ' . $summary);

      // Check that ::transformDimensions returns the expected dimensions.
      $variables = [
        '#theme' => 'image_style',
        '#style_name' => 'image_effects_test',
        '#uri' => $test_file_uris['border'],
        '#width' => $image->getWidth(),
        '#height' => $image->getHeight(),
      ];
      $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"{$test['expected_width']}\" height=\"{$test['expected_height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables), $test_description);

      // Check that ::applyEffect generates an image with the expected
      // dimensions.
      $this->testImageStyle->createDerivative($test_file_uris['border'], $derivative_uri);
      $derivative = $this->imageFactory->get($derivative_uri);
      $this->assertEquals($test['expected_width'], $derivative->getWidth(), $test_description);
      $this->assertEquals($test['expected_height'], $derivative->getHeight(), $test_description);

      // Check that ::applyEffect crops with the correct anchor.
      $gd_derivative = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertImagesAreEqual($test_images[$test['expected_image']], $gd_derivative, 1, $test_description);

      // Remove the effect for the next text.
      $this->removeEffectFromTestStyle($uuid);
    }
  }

}

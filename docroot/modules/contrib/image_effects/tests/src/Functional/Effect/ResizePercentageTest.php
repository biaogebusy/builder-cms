<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Resize percentage effect test.
 *
 * @group image_effects
 */
class ResizePercentageTest extends ImageEffectsTestBase {

  /**
   * Test the dimensions are resized properly.
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
  public function testResizePercentage($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);

    $test_data = [
      '100% scale on width' => [
        'effect_data' => [
          'width][c0][c1][value' => 100,
          'width][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 40,
        'expected_height' => 20,
      ],
      '50% scale on width' => [
        'effect_data' => [
          'width][c0][c1][value' => 50,
          'width][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 20,
        'expected_height' => 10,
      ],
      '150% scale on width' => [
        'effect_data' => [
          'width][c0][c1][value' => 150,
          'width][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 60,
        'expected_height' => 30,
      ],
      '100% scale on height' => [
        'effect_data' => [
          'height][c0][c1][value' => 100,
          'height][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 40,
        'expected_height' => 20,
      ],
      '50% scale on height' => [
        'effect_data' => [
          'height][c0][c1][value' => 50,
          'height][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 20,
        'expected_height' => 10,
      ],
      '150% scale on height' => [
        'effect_data' => [
          'height][c0][c1][value' => 150,
          'height][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 60,
        'expected_height' => 30,
      ],
      'Different % on width and height' => [
        'effect_data' => [
          'width][c0][c1][value' => 150,
          'width][c0][c1][uom' => 'perc',
          'height][c0][c1][value' => 50,
          'height][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 60,
        'expected_height' => 10,
      ],
      'Different px on width and height' => [
        'effect_data' => [
          'width][c0][c1][value' => 118,
          'width][c0][c1][uom' => 'px',
          'height][c0][c1][value' => 142,
          'height][c0][c1][uom' => 'px',
        ],
        'expected_width' => 118,
        'expected_height' => 142,
      ],
      'Fix width on px and height %' => [
        'effect_data' => [
          'width][c0][c1][value' => 118,
          'width][c0][c1][uom' => 'px',
          'height][c0][c1][value' => 150,
          'height][c0][c1][uom' => 'perc',
        ],
        'expected_width' => 118,
        'expected_height' => 30,
      ],
      'Fix height on px and width %' => [
        'effect_data' => [
          'width][c0][c1][value' => 150,
          'width][c0][c1][uom' => 'perc',
          'height][c0][c1][value' => 142,
          'height][c0][c1][uom' => 'px',
        ],
        'expected_width' => 60,
        'expected_height' => 142,
      ],
      'Fix width on px and height scaled' => [
        'effect_data' => [
          'width][c0][c1][value' => 80,
          'width][c0][c1][uom' => 'px',
        ],
        'expected_width' => 80,
        'expected_height' => 40,
      ],
      'Fix height on px and width scaled' => [
        'effect_data' => [
          'height][c0][c1][value' => 10,
          'height][c0][c1][uom' => 'px',
        ],
        'expected_width' => 20,
        'expected_height' => 10,
      ],
    ];

    foreach ($test_data as $test_description => $test) {
      // Add Resize percentage effect to the test image style.
      $effect = [
        'id' => 'image_effects_resize_percentage',
        'data' => $test['effect_data'],
      ];
      $uuid = $this->addEffectToTestStyle($effect);

      // Check that ::transformDimensions returns expected dimensions.
      $image = $this->imageFactory->get($original_uri);
      $this->assertEquals(40, $image->getWidth());
      $this->assertEquals(20, $image->getHeight());
      $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_uri));
      $variables = [
        '#theme' => 'image_style',
        '#style_name' => 'image_effects_test',
        '#uri' => $original_uri,
        '#width' => $image->getWidth(),
        '#height' => $image->getHeight(),
      ];
      $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"{$test['expected_width']}\" height=\"{$test['expected_height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables), $test_description);

      // Check that ::applyEffect generates image with expected dimensions.
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertEquals($test['expected_width'], $image->getWidth());
      $this->assertEquals($test['expected_height'], $image->getHeight());

      // Remove effect.
      $uuid = $this->removeEffectFromTestStyle($uuid);
    }
  }

}

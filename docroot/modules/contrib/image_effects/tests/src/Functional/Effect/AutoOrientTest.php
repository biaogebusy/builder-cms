<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Auto Orientation effect test.
 *
 * @group image_effects
 */
class AutoOrientTest extends ImageEffectsTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    static::$modules = array_merge(static::$modules, ['file_mdm', 'file_mdm_exif']);
    parent::setUp();
  }

  /**
   * Auto Orientation effect test.
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
  public function testAutoOrientEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Add Auto Orient effect to the test image style.
    $effect = [
      'id' => 'image_effects_auto_orient',
      'data' => [
        'scan_exif' => TRUE,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    // Add a scale effect too.
    $effect = [
      'id' => 'image_scale',
      'data' => [
        'width' => 200,
        'upscale' => TRUE,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    $test_data = [
      // Test a JPEG image with EXIF data.
      [
        'test_file' => $this->getTestImageCopyUri('/tests/images/portrait-painting.jpg', 'image_effects'),
        'original_width' => 640,
        'original_height' => 480,
        'derivative_width' => 200,
        'derivative_height' => 267,
      ],
      // Test a JPEG image without EXIF data.
      [
        'test_file' => $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.jpg'),
        'original_width' => 40,
        'original_height' => 20,
        'derivative_width' => 200,
        'derivative_height' => 100,
      ],
      // Test a non-EXIF image.
      [
        'test_file' => $this->getTestImageCopyUri('core/tests/fixtures/files/image-1.png'),
        'original_width' => 360,
        'original_height' => 240,
        'derivative_width' => 200,
        'derivative_height' => 133,
      ],
    ];

    foreach ($test_data as $data) {
      // Get URI of test file.
      $original_uri = $data['test_file'];

      // Test source image dimensions.
      $image = $this->imageFactory->get($original_uri);
      $this->assertEquals($data['original_width'], $image->getWidth());
      $this->assertEquals($data['original_height'], $image->getHeight());

      // Get expected derivative URL.
      $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_uri));

      // Check that ::transformDimensions returns expected dimensions.
      $variables = [
        '#theme' => 'image_style',
        '#style_name' => 'image_effects_test',
        '#uri' => $original_uri,
        '#width' => $image->getWidth(),
        '#height' => $image->getHeight(),
      ];
      $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"{$data['derivative_width']}\" height=\"{$data['derivative_height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables));

      // Check that ::applyEffect generates image with expected dimensions.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri);
      $this->assertEquals($data['derivative_width'], $image->getWidth());
      $this->assertEquals($data['derivative_height'], $image->getHeight());
    }
  }

  /**
   * Auto Orientation effect test, all EXIF orientation tags.
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
  public function testAutoOrientAllTags($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Add Auto Orient effect to the test image style.
    $effect = [
      'id' => 'image_effects_auto_orient',
      'data' => [
        'scan_exif' => TRUE,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    $test_data = [];
    for ($i = 1; $i < 9; $i++) {
      $test_data[$i]['test_file'] = $this->moduleList->getPath('image_effects') . "/tests/images/image-test-exif-orientation-$i.jpeg";
    }

    foreach ($test_data as $data) {
      // Get URI of test file.
      $original_uri = $data['test_file'];

      // Check that ::applyEffect generates image with expected dimensions and
      // colors at corners.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertEquals(120, $image->getWidth());
      $this->assertEquals(60, $image->getHeight());
      $this->assertColorsAreClose($this->red, $this->getPixelColor($image, 0, 0), 10);
      $this->assertColorsAreClose($this->green, $this->getPixelColor($image, 119, 0), 10);
      $this->assertColorsAreClose($this->yellow, $this->getPixelColor($image, 0, 59), 10);
      $this->assertColorsAreClose($this->blue, $this->getPixelColor($image, 119, 59), 10);
    }
  }

}

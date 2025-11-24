<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Component\Utility\Color;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Rotate effect test.
 *
 * @group image_effects
 */
class RotateTest extends ImageEffectsTestBase {

  /**
   * Rotate effect test.
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
  public function testRotateEffect(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // A list of files that will be tested.
    $files = [
      'image-test.png' => [
        'corners' => [$this->red, $this->green, $this->blue, $this->transparent],
        'transparency_supported' => TRUE,
        'color_tolerance' => 0,
      ],
      'image-test.gif' => [
        'corners' => [$this->red, $this->green, $this->blue, $this->transparent],
        'transparency_supported' => TRUE,
        'color_tolerance' => 48,
      ],
      'image-test-no-transparency.gif' => [
        'corners' => [$this->red, $this->green, $this->blue, $this->yellow],
        'transparency_supported' => TRUE,
        'color_tolerance' => 48,
      ],
      'image-test.jpg' => [
        'corners' => [$this->red, $this->green, $this->blue, $this->yellow],
        'transparency_supported' => FALSE,
        'color_tolerance' => 3,
      ],
      'img-test.webp' => [
        'corners' => [$this->red, $this->green, $this->blue, $this->transparent],
        'transparency_supported' => TRUE,
        // @todo WEBP on GD looses quality prior to PHP 8.1, need to check later
        // when PHP 8.1 is minimum version supported. Temporarily setting higher
        // tolerance. IM seems OK.
        'color_tolerance' => 675,
      ],
    ];

    // Test data.
    $test_data = [
      'rotate_5' => [
        // Fuchsia background.
        'arguments' => [
          'degrees' => 5,
          'transparent' => FALSE,
          'hex' => '#FF00FF',
          'opacity' => 100,
          'fallback' => '#FFFFFF',
        ],
        'expected_width' => 43,
        'expected_height' => 25,
        'corners_transform' => [$this, 'setFuchsia'],
      ],
      'rotate_minus_10' => [
        'arguments' => [
          'degrees' => -10,
          'transparent' => FALSE,
          'hex' => '#FF00FF',
          'opacity' => 100,
          'fallback' => '#FFFFFF',
        ],
        'expected_width' => 44,
        'expected_height' => 28,
        'corners_transform' => [$this, 'setFuchsia'],
      ],
      'rotate_90' => [
        // Fuchsia background.
        'arguments' => [
          'degrees' => 90,
          'transparent' => FALSE,
          'hex' => '#FF00FF',
          'opacity' => 100,
          'fallback' => '#FFFFFF',
        ],
        'expected_width' => 20,
        'expected_height' => 40,
        'corners_transform' => [$this, 'shift90'],
      ],
      'rotate_transparent_5' => [
        'arguments' => [
          'degrees' => 5,
          'transparent' => TRUE,
          'hex' => '#FFFFFF',
          'opacity' => 100,
          'fallback' => '#FFFFFF',
        ],
        'expected_width' => 43,
        'expected_height' => 25,
        'corners_transform' => [$this, 'setTransparent'],
      ],
      'rotate_transparent_5_cyan_fallback' => [
        'arguments' => [
          'degrees' => 5,
          'transparent' => TRUE,
          'hex' => '#FFFFFF',
          'opacity' => 100,
          'fallback' => '#00FFFF',
        ],
        'expected_width' => 43,
        'expected_height' => 25,
        'corners_transform' => [$this, 'setTransparent'],
      ],
      'rotate_transparent_90' => [
        'arguments' => [
          'degrees' => 90,
          'transparent' => TRUE,
          'hex' => '#FFFFFF',
          'opacity' => 100,
          'fallback' => '#FFFFFF',
        ],
        'expected_width' => 20,
        'expected_height' => 40,
        'corners_transform' => [$this, 'shift90'],
      ],
    ];

    foreach ($files as $file_name => $file_info) {
      // Get test image.
      try {
        $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/' . $file_name);
      }
      catch (FileNotExistsException $e) {
        // Earlier Drupal releases may miss some test files. In that case just
        // skip the test.
        continue;
      }

      foreach ($test_data as $test_description => $test) {
        $test_message = $file_name . ' - ' . $test_description;

        // Add Rotate effect to the test image style.
        $effect = [
          'id' => 'image_effects_rotate',
          'data' => [
            'degrees' => $test['arguments']['degrees'],
            'background_color][container][transparent' => $test['arguments']['transparent'],
            'background_color][container][hex' => $test['arguments']['hex'],
            'background_color][container][opacity' => $test['arguments']['opacity'],
            'transparency_fallback][fallback_transparency_color][hex'  => $test['arguments']['fallback'],
          ],
        ];
        $uuid = $this->addEffectToTestStyle($effect);

        // Check that ::transformDimensions returns expected dimensions.
        $image = $this->imageFactory->get($original_uri);
        if (!$image->isValid()) {
          // The image format may be not supported by the toolkit. Skip.
          continue;
        }
        $this->assertSame(40, $image->getWidth(), $test_message);
        $this->assertSame(20, $image->getHeight(), $test_message);
        $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_uri));
        $variables = [
          '#theme' => 'image_style',
          '#style_name' => 'image_effects_test',
          '#uri' => $original_uri,
          '#width' => $image->getWidth(),
          '#height' => $image->getHeight(),
        ];
        $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"{$test['expected_width']}\" height=\"{$test['expected_height']}\" alt=\"\" .*\/\>/", $this->getImageTag($variables), $test_message);

        // Check that ::applyEffect generates image with expected size and
        // color after rotation.
        $derivative_uri = $this->testImageStyle->buildUri($original_uri);
        $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
        $image = $this->imageFactory->get($derivative_uri, 'gd');
        $this->assertSame($test['expected_width'], $image->getWidth(), $test_message);
        $this->assertSame($test['expected_height'], $image->getHeight(), $test_message);

        $expected_corners = call_user_func($test['corners_transform'], $file_info['corners'], $file_info['transparency_supported'], $test['arguments']['fallback']);

        // *** GraphicsMagick-specific **** tweaks.
        if ($this->imageFactory->getToolkitId() === 'imagemagick' && \Drupal::configFactory()->get('imagemagick.settings')->get('binaries') === 'graphicsmagick') {
          // GraphicsMagick is a bit loose on color precision.
          $file_info['color_tolerance'] += 27;
          if (strpos($file_name, '.webp') !== FALSE) {
            $file_info['color_tolerance'] += 4800;
          }
          if ($test_description === 'rotate_transparent_5') {
            $file_info['color_tolerance'] = 70000;
          }
          // GIF goes beserk with transparent backgrounds.
          if (strpos($file_name, '.gif') !== FALSE && strpos($test_description, 'rotate_transparent_5') !== FALSE) {
            $this->removeEffectFromTestStyle($uuid);
            continue;
          }
        }

        // Check the colors at the image's corners.
        $this->assertColorsAreClose(
          $this->getPixelColor($image, 0, 0),
          $expected_corners[0],
          $file_info['color_tolerance'],
          $test_message
        );
        $this->assertColorsAreClose(
          $this->getPixelColor($image, $image->getWidth() - 1, 0),
          $expected_corners[1],
          $file_info['color_tolerance'],
          $test_message
        );
        $this->assertColorsAreClose(
          $this->getPixelColor($image, $image->getWidth() - 1, $image->getHeight() - 1),
          $expected_corners[2],
          $file_info['color_tolerance'],
          $test_message
        );
        $this->assertColorsAreClose(
          $this->getPixelColor($image, 0, $image->getHeight() - 1),
          $expected_corners[3],
          $file_info['color_tolerance'],
          $test_message
        );

        // Remove effect.
        $this->removeEffectFromTestStyle($uuid);
      }
    }
  }

  /**
   * Sets color corners to fuchsia.
   *
   * @param array $corners
   *   The image corners colors in RGBA format.
   * @param bool $transparency_supported
   *   TRUE if image supports full transparency.
   * @param string $fallback
   *   The fallback transparency color colors in RGB format.
   *
   * @return array
   *   The adjusted image corners colors.
   */
  private function setFuchsia(array $corners, bool $transparency_supported, string $fallback): array {
    return array_fill(0, 4, $this->fuchsia);
  }

  /**
   * Sets color corners to transparent.
   *
   * @param array $corners
   *   The image corners colors in RGBA format.
   * @param bool $transparency_supported
   *   TRUE if image supports full transparency.
   * @param string $fallback
   *   The fallback transparency color colors in RGB format.
   *
   * @return array
   *   The adjusted image corners colors.
   */
  private function setTransparent(array $corners, bool $transparency_supported, string $fallback): array {
    $temp = Color::hexToRgb($fallback);
    $fallback_rgba = [$temp['red'], $temp['blue'], $temp['green'], 0];
    return $transparency_supported ? array_fill(0, 4, $this->transparent) : array_fill(0, 4, $fallback_rgba);
  }

  /**
   * Shifts color corners by 90 degrees clockwise.
   *
   * @param array $corners
   *   The image corners colors in RGBA format.
   * @param bool $transparency_supported
   *   TRUE if image supports full transparency.
   * @param string $fallback
   *   The fallback transparency color colors in RGB format.
   *
   * @return array
   *   The adjusted image corners colors.
   */
  private function shift90(array $corners, bool $transparency_supported, string $fallback): array {
    return [
      $corners[3],
      $corners[0],
      $corners[1],
      $corners[2],
    ];
  }

  /**
   * Random rotation test.
   */
  public function testRandomRotate(): void {

    // Add Rotate effect to the test image style.
    $effect = [
      'id' => 'image_effects_rotate',
      'data' => [
        'degrees' => 5,
        'method' => 'random',
      ],
    ];
    $this->addEffectToTestStyle($effect);

    // Check that ::transformDimensions returns expected dimensions.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');
    $image = $this->imageFactory->get($original_uri);
    $this->assertSame(40, $image->getWidth());
    $this->assertSame(20, $image->getHeight());
    $variables = [
      '#theme' => 'image_style',
      '#style_name' => 'image_effects_test',
      '#uri' => $original_uri,
      '#width' => $image->getWidth(),
      '#height' => $image->getHeight(),
    ];
    $this->assertStringNotContainsString("width=", $this->getImageTag($variables));
    $this->assertStringNotContainsString("height=", $this->getImageTag($variables));
  }

  /**
   * Pseudorandom rotation test.
   */
  public function testPseudorandomRotate(): void {

    // Add Rotate effect to the test image style.
    $effect = [
      'id' => 'image_effects_rotate',
      'data' => [
        'degrees' => 60,
        'method' => 'pseudorandom',
      ],
    ];
    $this->addEffectToTestStyle($effect);

    // Check that ::transformDimensions returns expected dimensions.
    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');
    $image = $this->imageFactory->get($original_uri);
    $this->assertSame(40, $image->getWidth());
    $this->assertSame(20, $image->getHeight());
    $derivative_url = $this->fileUrlGenerator->transformRelative($this->testImageStyle->buildUrl($original_uri));
    $variables = [
      '#theme' => 'image_style',
      '#style_name' => 'image_effects_test',
      '#uri' => $original_uri,
      '#width' => $image->getWidth(),
      '#height' => $image->getHeight(),
    ];
    $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"42\" height=\"23\" alt=\"\" .*\/\>/", $this->getImageTag($variables));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Background effect test.
 *
 * @group image_effects
 */
class BackgroundTest extends ImageEffectsTestBase {

  /**
   * {@inheritdoc}
   */
  public static function providerToolkits(): array {
    $toolkits = parent::providerToolkits();
    // @todo This effect does not work on GraphicsMagick.
    unset($toolkits['ImageMagick-graphicsmagick']);
    return $toolkits;
  }

  /**
   * Background effect test.
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
  public function testBackgroundEffect(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $original_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png');
    $background_uri = $this->getTestImageCopyUri('core/tests/fixtures/files/image-1.png');

    $effect = [
      'id' => 'image_effects_background',
      'data' => [
        'placement' => 'left-top',
        'x_offset' => 0,
        'y_offset' => 0,
        'opacity' => 100,
        'background_image' => $background_uri,
      ],
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
    $this->assertMatchesRegularExpression("/\<img src=\"" . preg_quote($derivative_url, '/') . "\" width=\"360\" height=\"240\" alt=\"\" .*\/\>/", $this->getImageTag($variables));

    // Check that ::applyEffect generates image with expected canvas.
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertEquals(360, $image->getWidth());
    $this->assertEquals(240, $image->getHeight());
    $this->assertColorsAreEqual($this->red, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->green, $this->getPixelColor($image, 39, 0));
    $this->assertColorsAreEqual(
      [185, 185, 185, 0],
      $this->getPixelColor($image, 0, 19)
    );
    $this->assertColorsAreEqual($this->blue, $this->getPixelColor($image, 39, 19));

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);

    // Toolkit-specific tests.
    switch ($this->imageFactory->getToolkitId()) {
      case 'gd':
        // For the GD toolkit, test we are not left with orphan image after
        // applying the operation.
        $image = $this->imageFactory->get($original_uri);
        // Apply the operation.
        $image->apply('background', [
          'x_offset' => 0,
          'y_offset' => 0,
          'opacity' => 100,
          'background_image' => $this->imageFactory->get($background_uri),
        ]);
        // Save image and compare against original, should differ.
        $this->assertTrue($image->save($original_uri . '.modified.png'));
        $image_original = $this->imageFactory->get($original_uri);
        $image_modified = $this->imageFactory->get($original_uri . '.modified.png');
        $this->assertImagesAreNotEqual($image_original, $image_modified);
        break;

      case 'imagemagick':
        // For the Imagemagick toolkit, toolkit should return background
        // image dimensions after applying the operation, but before
        // saving.
        $image = $this->imageFactory->get($original_uri);
        // Apply the operation.
        $image->apply('background', [
          'x_offset' => 0,
          'y_offset' => 0,
          'opacity' => 100,
          'background_image' => $this->imageFactory->get($background_uri),
        ]);
        $this->assertEquals(360, $image->getToolkit()->getWidth());
        $this->assertEquals(240, $image->getToolkit()->getHeight());
        break;

    }
  }

}

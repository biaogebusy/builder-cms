<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Strip metadata effect test.
 *
 * @group image_effects
 */
class StripMetadataTest extends ImageEffectsTestBase {

  /**
   * Strip metadata effect test.
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
  public function testStripMetadataEffect(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    // Add Strip metadata effect to the test image style.
    $effect = [
      'id' => 'image_effects_strip_metadata',
    ];
    $this->addEffectToTestStyle($effect);

    $test_data = [
      // Test a JPEG image with EXIF data.
      [
        'test_file' => $this->getTestImageCopyUri('/tests/images/portrait-painting.jpg', 'image_effects'),
        'original_orientation' => 8,
      ],
      // Test a JPEG image without EXIF data.
      [
        'test_file' => $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.jpg'),
        'original_orientation' => NULL,
      ],
      // Test a non-EXIF image.
      [
        'test_file' => $this->getTestImageCopyUri('core/tests/fixtures/files/image-1.png'),
        'original_orientation' => NULL,
      ],
    ];

    foreach ($test_data as $data) {
      // Get expected URIs.
      $original_uri = $data['test_file'];
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);

      // Test source image EXIF data.
      $exif = @exif_read_data(\Drupal::service('file_system')->realpath($original_uri));
      $this->assertEquals($data['original_orientation'], $exif['Orientation'] ?? NULL);

      // Process source image.
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);

      // Check that ::applyEffect strips EXIF metadata.
      $exif = @exif_read_data(\Drupal::service('file_system')->realpath($derivative_uri));
      $this->assertEquals(NULL, $exif['Orientation'] ?? NULL);
    }
  }

}

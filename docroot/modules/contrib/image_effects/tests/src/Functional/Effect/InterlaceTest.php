<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Interlace effect test.
 *
 * @group image_effects
 */
class InterlaceTest extends ImageEffectsTestBase {

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
   * Interlace effect test.
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
  public function testInterlaceEffect($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);

    $test_data = [
      // Test on the PNG test image.
      [
        'test_file' => $this->getTestImageCopyUri('core/tests/fixtures/files/image-test.png'),
      ],
    ];

    // Add interlace effect to the test image style.
    $effect = [
      'id' => 'image_effects_interlace',
      'data' => [
        'type' => 'Plane',
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    foreach ($test_data as $data) {
      $original_uri = $data['test_file'];

      // Check that ::applyEffect generates interlaced PNG or GIF or
      // progressive JPEG image.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');

      $this->assertTrue($this->isPngInterlaced($image));
    }

    // Remove effect.
    $uuid = $this->removeEffectFromTestStyle($uuid);
  }

  /**
   * Checks if this is an interlaced PNG.
   *
   * @param \Drupal\Core\Image\ImageInterface $image
   *   An image object that need to be checked.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   *
   * @see http://stackoverflow.com/questions/14235600/php-test-if-image-is-interlaced
   */
  protected function isPngInterlaced(ImageInterface $image) {
    $source = $image->getSource();

    $real_path = $this->container->get('file_system')->realpath($source);

    $handle = fopen($real_path, "r");
    $contents = fread($handle, 32);
    fclose($handle);
    return ord($contents[28]) != 0;
  }

}

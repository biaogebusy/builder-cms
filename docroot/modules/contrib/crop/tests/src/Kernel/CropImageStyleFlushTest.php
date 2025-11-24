<?php

namespace Drupal\Tests\crop\Kernel;

use Drupal\crop\Entity\Crop;

/**
 * Tests automatic flushing of image style derivatives when crop is saved.
 *
 * @group crop
 */
class CropImageStyleFlushTest extends CropUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['user', 'image', 'crop', 'file', 'system'];

  /**
   * Additional test image style.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $testStyle2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install system and crop settings config.
    $this->installConfig(['system', 'crop']);

    // Enable automatic flushing of image style derivatives.
    $this->config('crop.settings')
      ->set('flush_derivative_images', TRUE)
      ->save();

    // Create a second image style with the same crop type.
    $uuid = $this->container->get('uuid')->generate();
    $this->testStyle2 = $this->imageStyleStorage->create([
      'name' => 'test_style_2',
      'label' => 'Test image style 2',
      'effects' => [
        $uuid => [
          'id' => 'crop_crop',
          'data' => ['crop_type' => 'test_type'],
          'weight' => 0,
          'uuid' => $uuid,
        ],
      ],
    ]);
    $this->testStyle2->save();
  }

  /**
   * Tests that image style derivatives are flushed when crop is saved.
   */
  public function testImageStyleFlushOnCropSave(): void {
    // Create test file.
    $file = $this->getTestFile();
    $file->save();
    $file_uri = $file->getFileUri();

    // Create crop for the file.
    $crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $file->id(),
      'entity_type' => 'file',
      'uri' => $file_uri,
      'x' => '100',
      'y' => '100',
      'width' => '50',
      'height' => '50',
    ]);
    $crop->save();

    // Create derivative images for both image styles.
    $derivative_uri_1 = $this->testStyle->buildUri($file_uri);
    $derivative_uri_2 = $this->testStyle2->buildUri($file_uri);

    // Generate the derivatives.
    $this->testStyle->createDerivative($file_uri, $derivative_uri_1);
    $this->testStyle2->createDerivative($file_uri, $derivative_uri_2);

    // Verify both derivatives exist.
    $this->assertFileExists($derivative_uri_1, 'First image style derivative exists.');
    $this->assertFileExists($derivative_uri_2, 'Second image style derivative exists.');

    // Update the crop - this should trigger imageStylePathFlush().
    $crop->setPosition(150, 150)->save();

    // Verify derivatives have been flushed (deleted).
    $this->assertFileDoesNotExist($derivative_uri_1, 'First image style derivative was flushed after crop update.');
    $this->assertFileDoesNotExist($derivative_uri_2, 'Second image style derivative was flushed after crop update.');
  }

  /**
   * Tests that only derivatives with matching crop type are flushed.
   */
  public function testImageStyleFlushOnlyMatchingCropType(): void {
    // Create a different crop type.
    $crop_type_2 = $this->cropTypeStorage->create([
      'id' => 'test_type_2',
      'label' => 'Test crop type 2',
      'description' => 'Another crop type.',
    ]);
    $crop_type_2->save();

    // Create an image style with the different crop type.
    $uuid = $this->container->get('uuid')->generate();
    $style_different_type = $this->imageStyleStorage->create([
      'name' => 'test_style_different',
      'label' => 'Test image style with different crop type',
      'effects' => [
        $uuid => [
          'id' => 'crop_crop',
          'data' => ['crop_type' => 'test_type_2'],
          'weight' => 0,
          'uuid' => $uuid,
        ],
      ],
    ]);
    $style_different_type->save();

    // Create test file.
    $file = $this->getTestFile();
    $file->save();
    $file_uri = $file->getFileUri();

    // Create crop for test_type.
    $crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $file->id(),
      'entity_type' => 'file',
      'uri' => $file_uri,
      'x' => '100',
      'y' => '100',
      'width' => '50',
      'height' => '50',
    ]);
    $crop->save();

    // Create a crop for test_type_2 so its derivative can be generated.
    // Image styles with crop effects need a crop to exist to create
    // derivatives.
    $crop2 = Crop::create([
      'type' => $crop_type_2->id(),
      'entity_id' => $file->id(),
      'entity_type' => 'file',
      'uri' => $file_uri,
      'x' => '100',
      'y' => '100',
      'width' => '50',
      'height' => '50',
    ]);
    $crop2->save();

    // Create derivatives for both styles.
    $derivative_uri_matching = $this->testStyle->buildUri($file_uri);
    $derivative_uri_different = $style_different_type->buildUri($file_uri);

    $this->testStyle->createDerivative($file_uri, $derivative_uri_matching);
    $style_different_type->createDerivative($file_uri, $derivative_uri_different);

    // Verify both derivatives exist.
    $this->assertFileExists($derivative_uri_matching, 'Matching crop type derivative exists.');
    $this->assertFileExists($derivative_uri_different, 'Different crop type derivative exists.');

    // Update the crop for test_type (not test_type_2).
    $crop->setPosition(150, 150)->save();

    // Verify only the matching crop type derivative was flushed.
    $this->assertFileDoesNotExist($derivative_uri_matching, 'Matching crop type derivative was flushed.');
    $this->assertFileExists($derivative_uri_different, 'Different crop type derivative was NOT flushed.');
  }

  /**
   * Tests that flush is skipped when flush_derivative_images is disabled.
   */
  public function testImageStyleFlushCanBeDisabled(): void {
    // Disable automatic flushing.
    $this->config('crop.settings')
      ->set('flush_derivative_images', FALSE)
      ->save();

    // Create test file.
    $file = $this->getTestFile();
    $file->save();
    $file_uri = $file->getFileUri();

    // Create crop for the file.
    $crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $file->id(),
      'entity_type' => 'file',
      'uri' => $file_uri,
      'x' => '100',
      'y' => '100',
      'width' => '50',
      'height' => '50',
    ]);
    $crop->save();

    // Create derivative image.
    $derivative_uri = $this->testStyle->buildUri($file_uri);
    $this->testStyle->createDerivative($file_uri, $derivative_uri);

    // Verify derivative exists.
    $this->assertFileExists($derivative_uri, 'Image style derivative exists.');

    // Update the crop - flush should NOT happen because config is disabled.
    $crop->setPosition(150, 150);
    $crop->save();

    // Verify derivative still exists (was NOT flushed).
    $this->assertFileExists($derivative_uri, 'Image style derivative still exists when flushing is disabled.');
  }

  /**
   * Tests that new crops trigger flush.
   */
  public function testImageStyleFlushOnNewCrop(): void {
    // Create test file.
    $file = $this->getTestFile();
    $file->save();
    $file_uri = $file->getFileUri();

    // Create a derivative BEFORE crop exists (e.g., default image).
    $derivative_uri = $this->testStyle->buildUri($file_uri);
    $this->testStyle->createDerivative($file_uri, $derivative_uri);

    // Verify derivative exists.
    $this->assertFileExists($derivative_uri, 'Image style derivative exists before crop.');

    // Now create a new crop - this should flush the derivative.
    $crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $file->id(),
      'entity_type' => 'file',
      'uri' => $file_uri,
      'x' => '100',
      'y' => '100',
      'width' => '50',
      'height' => '50',
    ]);
    $crop->save();

    // Verify derivative has been flushed.
    $this->assertFileDoesNotExist($derivative_uri, 'Image style derivative was flushed when new crop was created.');
  }

  /**
   * Tests that image styles without crop effects are not flushed.
   */
  public function testImageStyleWithoutCropEffectNotFlushed(): void {
    // Create an image style without any crop effect.
    $uuid_scale = $this->container->get('uuid')->generate();
    $style_no_crop = $this->imageStyleStorage->create([
      'name' => 'test_style_no_crop',
      'label' => 'Test image style without crop',
      'effects' => [
        $uuid_scale => [
          'id' => 'image_scale',
          'data' => ['width' => 100, 'height' => 100],
          'weight' => 0,
          'uuid' => $uuid_scale,
        ],
      ],
    ]);
    $style_no_crop->save();

    // Create test file.
    $file = $this->getTestFile();
    $file->save();
    $file_uri = $file->getFileUri();

    // Create crop for the file.
    $crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $file->id(),
      'entity_type' => 'file',
      'uri' => $file_uri,
      'x' => '100',
      'y' => '100',
      'width' => '50',
      'height' => '50',
    ]);
    $crop->save();

    // Create derivatives for both styles.
    $derivative_uri_with_crop = $this->testStyle->buildUri($file_uri);
    $derivative_uri_no_crop = $style_no_crop->buildUri($file_uri);

    $this->testStyle->createDerivative($file_uri, $derivative_uri_with_crop);
    $style_no_crop->createDerivative($file_uri, $derivative_uri_no_crop);

    // Verify both derivatives exist.
    $this->assertFileExists($derivative_uri_with_crop, 'Derivative with crop effect exists.');
    $this->assertFileExists($derivative_uri_no_crop, 'Derivative without crop effect exists.');

    // Update the crop.
    $crop->setPosition(150, 150);
    $crop->save();

    // Verify only the crop-based derivative was flushed.
    $this->assertFileDoesNotExist($derivative_uri_with_crop, 'Derivative with crop effect was flushed.');
    $this->assertFileExists($derivative_uri_no_crop, 'Derivative without crop effect was NOT flushed.');
  }

}

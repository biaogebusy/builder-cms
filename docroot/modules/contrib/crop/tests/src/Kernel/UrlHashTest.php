<?php

namespace Drupal\Tests\crop\Kernel;

use Drupal\Component\Utility\UrlHelper;
use Drupal\crop\Entity\Crop;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the image URL contains a short hash of the crop.
 */
#[Group('crop')]
#[CoversFunction('crop_file_url_alter')]
class UrlHashTest extends CropUnitTestBase {

  /**
   * Crop used in test.
   */
  protected Crop $crop;

  /**
   * File to apply effects.
   */
  protected FileInterface $file;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['user', 'image', 'crop', 'file', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create image to be cropped.
    $this->file = $this->getTestFile();
    $this->file->save();

    $this->crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $this->file->id(),
      'entity_type' => 'file',
      'uri' => $this->file->getFileUri(),
      'x' => '190',
      'y' => '120',
      'width' => '50',
      'height' => '50',
    ]);
    $this->crop->save();
  }

  /**
   * Tests that an image style URL with a crop effect has the short hash.
   */
  public function testCropUrlHashCrop(): void {
    $image_style_url = $this->testStyle->buildUrl($this->file->getFileUri());
    $url = UrlHelper::parse($image_style_url);
    $this->assertSame($this->crop->getShortHash(), $url['query']['h']);
  }

  /**
   * Tests image url with crop, convert effects has hash.
   */
  public function testCropUrlHashCropConvert() {
    // Add effect to convert image to jpeg.
    $effect = [
      'id' => 'image_convert',
      'weight' => 0,
      'data' => [
        'extension' => 'jpeg',
      ],
    ];
    $this->testStyle->addImageEffect($effect);
    $this->testStyle->save();

    $image_style_url = $this->testStyle->buildUrl($this->file->getFileUri());
    $url = UrlHelper::parse($image_style_url);
    $this->assertSame($this->crop->getShortHash(), $url['query']['h']);
  }

}

<?php

namespace Drupal\Tests\video_embed_media\Kernel;

use Drupal\Tests\media\Kernel\MediaKernelTestBase;
use Drupal\media\Entity\Media;

/**
 * Test the media bundle default names.
 *
 * @group video_embed_media
 */
class DefaultNameTest extends MediaKernelTestBase {

  /**
   * The plugin under test.
   *
   * @var \Drupal\video_embed_media\Plugin\media\Source\VideoEmbedField
   */
  protected $plugin;

  /**
   * The created media type.
   *
   * @var \Drupal\media\Entity\MediaType
   */
  protected $entityType;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'video_embed_field',
    'video_embed_media',
    'media',
    'file',
    'views',
  ];

  /**
   * Test cases for ::testDefaultName().
   */
  public static function defaultNameTestCases() {
    return [
      'YouTube' => [
        'https://www.youtube.com/watch?v=gnERPdAiuSo',
        'YouTube | DrupalCon Austin 2014: Keynote: Dries Buytaert',
      ],
      'YouTube - youtu.be' => [
        'https://www.youtu.be/gnERPdAiuSo',
        'YouTube | DrupalCon Austin 2014: Keynote: Dries Buytaert',
      ],
      'Youtube Playlist' => [
        'https://www.youtube.com/watch?v=bbOCX3vlMyE&list=PLpeDXSh4nHjS7I_OIrHrZXb00xSDZ7zBz',
        'YouTube Playlist | DrupalCon',
      ],
      'Vimeo' => [
        'https://vimeo.com/21681203',
        'Vimeo | Drupal Commerce at DrupalCon Chicago',
      ],
    ];
  }

  /**
   * Test the default name.
   *
   * @dataProvider defaultNameTestCases
   */
  public function testDefaultName($input, $expected) {
    $field_name = $this->plugin->getSourceFieldDefinition($this->entityType)->getName();
    $entity = Media::create([
      'bundle' => $this->entityType->id(),
      $field_name => [['value' => $input]],
    ]);
    $actual = $this->plugin->getMetadata($entity, 'default_name');
    $this->assertEquals($expected, $actual);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['video_embed_field', 'video_embed_media']);

    $this->entityType = $this->createMediaType('video_embed_field');

    $this->plugin = $this->entityType->getSource();
  }

}

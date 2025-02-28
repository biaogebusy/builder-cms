<?php

namespace Drupal\Tests\media_entity_instagram\Functional;

use Drupal\Tests\media\Functional\FieldFormatter\OEmbedFormatterTest;

/**
 * Tests for Instagram embed formatter.
 *
 * @group media_entity_instagram
 */
class InstagramEmbedFormatterTest extends OEmbedFormatterTest {

  /**
   * {@inheritdoc}
   */
  protected function getFixturesDirectory() {
    return \Drupal::service('extension.list.module')->getPath('media_entity_instagram') . '/tests/fixtures/oembed';
  }

  /**
   * {@inheritdoc}
   */
  public function providerRender() {
    return [
      'Instagram' => [
        'https://instagram.com/p/B2huuS8AQVq',
        'instagram.json',
        [],
        [
          'iframe' => [
            'src' => '/media/oembed?url=https%3A//instagram.com/p/B2huuS8AQVq',
            'width' => '658',
          ],
        ],
      ],
    ];
  }

}

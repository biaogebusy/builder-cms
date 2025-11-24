<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\Event;

use Drupal\elasticsearch_connector\Event\AlterSettingsEvent;
use Drupal\elasticsearch_connector\Event\SynonymsSubscriber;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the SynonymsSubscriber.
 *
 * @group elasticsearch_connector
 * @coversDefaultClass \Drupal\elasticsearch_connector\Event\SynonymsSubscriber
 */
class SynonymsSubscriberTest extends UnitTestCase {

  /**
   * The index that was created.
   */
  protected IndexInterface $index;

  /**
   * @covers ::onAlterSettings
   */
  public function testOnSettingsAlter(): void {
    $synonyms = ['foo, bar', 'cat, dog'];

    // Provide some existing settings to test array merging.
    $settings = [
      'whiz' => 'bang',
      'analysis' => [
        'filter' => ['foo' => ['bar']],
      ],
    ];

    $backendConfig = ['advanced' => ['synonyms' => $synonyms]];
    $index = $this->index = $this->createMock(IndexInterface::class);
    $event = new AlterSettingsEvent($settings, $backendConfig, $index);

    $subscriber = new SynonymsSubscriber();
    $subscriber->onAlterSettings($event);

    $settings = $event->getSettings();

    $expectedSettings = [
      'whiz' => 'bang',
      'analysis' => [
        'filter' => [
          'foo' => ['bar'],
          'synonyms' => [
            'type' => 'synonym_graph',
            'lenient' => TRUE,
            'synonyms' => $synonyms,
          ],
        ],
        'analyser' => [
          'querytime_synonyms' => [
            'type' => 'custom',
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'asciifolding', 'synonyms'],
          ],
        ],
      ],
    ];

    $this->assertEquals($expectedSettings, $settings);
    $this->assertEquals($index, $event->getIndex());

  }

}

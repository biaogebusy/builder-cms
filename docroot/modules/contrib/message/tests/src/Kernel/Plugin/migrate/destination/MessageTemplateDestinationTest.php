<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Plugin\migrate\destination;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Plugin\migrate\destination\MessageTemplateDestination;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for the message template migrate destination.
 *
 * @coversDefaultClass \Drupal\message\Plugin\migrate\destination\MessageTemplateDestination
 *
 * @group Message
 */
class MessageTemplateDestinationTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'migrate',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
  }

  /**
   * Tests updateEntity applies destination text onto the entity.
   *
   * @covers ::updateEntity
   */
  public function testUpdateEntitySetsText(): void {
    $template = $this->createMessageTemplate(
      'migrate_dest',
      'Migrate destination',
      'Description',
      ['Original text']
    );

    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $destination = MessageTemplateDestination::create(
      $this->container,
      ['plugin' => 'entity:message_template'],
      'entity:message_template',
      [],
      $migration
    );

    $row = new Row([], []);
    $row->setDestinationProperty('text', [
      [
        'value' => 'Migrated text',
        'format' => filter_default_format(),
      ],
    ]);

    $method = new \ReflectionMethod($destination, 'updateEntity');
    $method->invoke($destination, $template, $row);

    $text = $template->get('text');
    $this->assertEquals('Migrated text', $text[0]['value']);
  }

  /**
   * Tests updateEntity leaves text alone when destination omits it.
   *
   * @covers ::updateEntity
   */
  public function testUpdateEntityWithoutText(): void {
    $template = $this->createMessageTemplate(
      'migrate_dest_no_text',
      'Migrate destination',
      'Description',
      ['Keep this']
    );

    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $destination = MessageTemplateDestination::create(
      $this->container,
      ['plugin' => 'entity:message_template'],
      'entity:message_template',
      [],
      $migration
    );

    $row = new Row([], []);
    $method = new \ReflectionMethod($destination, 'updateEntity');
    $method->invoke($destination, $template, $row);

    $this->assertEquals('Keep this', $template->get('text')[0]['value']);
  }

}

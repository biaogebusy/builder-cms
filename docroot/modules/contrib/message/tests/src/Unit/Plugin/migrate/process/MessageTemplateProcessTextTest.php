<?php

namespace Drupal\Tests\message\Unit\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\message\MessageTemplateInterface;
use Drupal\message\Plugin\migrate\process\MessageTemplateProcessText;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for the d7_message_template_text process plugin.
 *
 * @coversDefaultClass \Drupal\message\Plugin\migrate\process\MessageTemplateProcessText
 *
 * @group Message
 */
class MessageTemplateProcessTextTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests appending text when no existing template is present.
   *
   * @covers ::transform
   */
  public function testTransformWithoutExistingTemplate(): void {
    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('example')->willReturn(NULL);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('message_template')->willReturn($storage->reveal());

    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $plugin = new MessageTemplateProcessText(
      [],
      'd7_message_template_text',
      [],
      $migration,
      $entity_type_manager->reveal()
    );

    $row = $this->prophesize(Row::class);
    $row->getSource()->willReturn([
      'name' => 'example',
      'message_text_value' => 'Hello world',
      'message_text_format' => 'filtered_html',
    ]);

    $result = $plugin->transform(
      NULL,
      $this->prophesize(MigrateExecutableInterface::class)->reveal(),
      $row->reveal(),
      'text'
    );

    $this->assertEquals([
      [
        'value' => 'Hello world',
        'format' => 'filtered_html',
      ],
    ], $result);
  }

  /**
   * Tests appending text onto an existing template's raw text.
   *
   * @covers ::transform
   */
  public function testTransformWithExistingTemplate(): void {
    $template = $this->prophesize(MessageTemplateInterface::class);
    $template->getRawText()->willReturn([
      [
        'value' => 'Existing',
        'format' => 'plain_text',
      ],
    ]);

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('example')->willReturn($template->reveal());

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('message_template')->willReturn($storage->reveal());

    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $plugin = new MessageTemplateProcessText(
      [],
      'd7_message_template_text',
      [],
      $migration,
      $entity_type_manager->reveal()
    );

    $row = $this->prophesize(Row::class);
    $row->getSource()->willReturn([
      'name' => 'example',
      'message_text_value' => 'Appended',
      'message_text_format' => 'filtered_html',
    ]);

    $result = $plugin->transform(
      NULL,
      $this->prophesize(MigrateExecutableInterface::class)->reveal(),
      $row->reveal(),
      'text'
    );

    $this->assertEquals([
      [
        'value' => 'Existing',
        'format' => 'plain_text',
      ],
      [
        'value' => 'Appended',
        'format' => 'filtered_html',
      ],
    ], $result);
  }

  /**
   * Tests container factory wiring.
   *
   * @covers ::create
   * @covers ::__construct
   */
  public function testCreate(): void {
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    $container = $this->prophesize(ContainerInterface::class);
    $container->get('entity_type.manager')->willReturn($entity_type_manager);

    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $plugin = MessageTemplateProcessText::create(
      $container->reveal(),
      [],
      'd7_message_template_text',
      [],
      $migration
    );

    $this->assertInstanceOf(MessageTemplateProcessText::class, $plugin);
  }

}

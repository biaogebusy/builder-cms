<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\MessageTemplateListBuilder;

/**
 * Kernel tests for the message template list builder.
 *
 * @coversDefaultClass \Drupal\message\MessageTemplateListBuilder
 *
 * @group Message
 */
class MessageTemplateListBuilderTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'system',
    'user',
  ];

  /**
   * The list builder under test.
   *
   * @var \Drupal\message\MessageTemplateListBuilder
   */
  protected MessageTemplateListBuilder $listBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');

    $entity_type = $this->container->get('entity_type.manager')
      ->getDefinition('message_template');
    $this->listBuilder = MessageTemplateListBuilder::createInstance(
      $this->container,
      $entity_type
    );
  }

  /**
   * Tests header and row construction.
   *
   * @covers ::createInstance
   * @covers ::buildHeader
   * @covers ::buildRow
   */
  public function testHeaderAndRow(): void {
    $header = $this->listBuilder->buildHeader();
    $this->assertArrayHasKey('title', $header);
    $this->assertArrayHasKey('description', $header);

    $template = $this->createMessageTemplate(
      'tpl_list',
      'Template label',
      'Template description',
      ['Some text']
    );

    $row = $this->listBuilder->buildRow($template);
    $this->assertEquals('Template label', $row['title']['data']);
    $this->assertEquals('Template description', $row['description']);
  }

}

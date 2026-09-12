<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\message\Entity\Message;
use Drupal\message\MessageListBuilder;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for the message entity list builder.
 *
 * @coversDefaultClass \Drupal\message\MessageListBuilder
 *
 * @group Message
 */
class MessageListBuilderTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'language',
    'message',
    'system',
    'user',
  ];

  /**
   * The list builder under test.
   *
   * @var \Drupal\message\MessageListBuilder
   */
  protected MessageListBuilder $listBuilder;

  /**
   * A message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'language', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');

    $this->messageTemplate = $this->createMessageTemplate(
      'list_builder_tpl',
      'List builder template',
      'Description',
      ['List builder text']
    );

    $entity_type = $this->container->get('entity_type.manager')
      ->getDefinition('message');
    $this->listBuilder = MessageListBuilder::createInstance(
      $this->container,
      $entity_type
    );
  }

  /**
   * Tests header columns for a single-language site.
   *
   * @covers ::createInstance
   * @covers ::buildHeader
   */
  public function testBuildHeader(): void {
    $header = $this->listBuilder->buildHeader();

    $this->assertArrayHasKey('created', $header);
    $this->assertArrayHasKey('text', $header);
    $this->assertArrayHasKey('template', $header);
    $this->assertArrayHasKey('author', $header);
    $this->assertArrayNotHasKey('language_name', $header);
  }

  /**
   * Tests the language column when the site is multilingual.
   *
   * @covers ::buildHeader
   */
  public function testBuildHeaderMultilingual(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->assertTrue($this->container->get('language_manager')->isMultilingual());

    $header = $this->listBuilder->buildHeader();
    $this->assertArrayHasKey('language_name', $header);
  }

  /**
   * Tests row values for owned and anonymous messages.
   *
   * Note: buildHeader() keys the created column as "created", but buildRow()
   * currently keys the same cell as "changed". Assert current behavior so a
   * future source fix can update this expectation.
   *
   * @covers ::buildRow
   */
  public function testBuildRow(): void {
    $account = $this->createUser([], 'list_author');
    $owned = Message::create([
      'template' => $this->messageTemplate->id(),
      'uid' => $account->id(),
    ]);
    $owned->save();

    $row = $this->listBuilder->buildRow($owned);
    // Header uses "created"; row currently uses "changed".
    $this->assertArrayHasKey('changed', $row);
    $this->assertArrayNotHasKey('created', $row);
    $this->assertArrayHasKey('text', $row);
    $this->assertEquals('List builder template', $row['template']);
    $this->assertEquals('list_author', $row['author']);

    $anonymous = Message::create([
      'template' => $this->messageTemplate->id(),
      'uid' => 0,
    ]);
    $anonymous->save();
    $anonymous_row = $this->listBuilder->buildRow($anonymous);
    $this->assertEquals('Anonymous', (string) $anonymous_row['author']);
  }

}

<?php

namespace Drupal\Tests\message\Kernel\Plugin\QueueWorker;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Plugin\QueueWorker\MessageDeletionWorker;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Tests the message deletion queue worker.
 *
 * @coversDefaultClass \Drupal\message\Plugin\QueueWorker\MessageDeletionWorker
 *
 * @group Message
 */
class MessageDeletionWorkerTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'user', 'system'];

  /**
   * The queue worker under test.
   *
   * @var \Drupal\message\Plugin\QueueWorker\MessageDeletionWorker
   */
  protected $plugin;

  /**
   * A message template ID.
   *
   * @var string
   */
  protected $templateId;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('user');

    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', ['sequences']);
    }

    $template = $this->createMessageTemplate(
      mb_strtolower($this->randomMachineName()),
      $this->randomString(),
      $this->randomString(),
      ['Dummy text']
    );
    $this->templateId = $template->id();
    $this->createPlugin();
  }

  /**
   * Tests the create() factory method.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $plugin = MessageDeletionWorker::create(
      $this->container,
      [],
      'message_delete',
      []
    );
    $this->assertInstanceOf(MessageDeletionWorker::class, $plugin);
  }

  /**
   * Tests that empty data is a no-op.
   *
   * @covers ::processItem
   */
  public function testEmptyData(): void {
    $this->plugin->processItem(NULL);
    $this->plugin->processItem([]);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests deleting messages by ID.
   *
   * @covers ::processItem
   */
  public function testProcessItem(): void {
    $message_one = Message::create(['template' => $this->templateId]);
    $message_one->save();
    $message_two = Message::create(['template' => $this->templateId]);
    $message_two->save();
    $message_three = Message::create(['template' => $this->templateId]);
    $message_three->save();

    $this->plugin->processItem([
      $message_one->id(),
      $message_two->id(),
    ]);

    $this->assertNull(Message::load($message_one->id()));
    $this->assertNull(Message::load($message_two->id()));
    $this->assertNotNull(Message::load($message_three->id()));
  }

  /**
   * Creates the plugin instance from the queue worker manager.
   */
  protected function createPlugin(): void {
    $this->plugin = $this->container
      ->get('plugin.manager.queue_worker')
      ->createInstance('message_delete');
  }

}

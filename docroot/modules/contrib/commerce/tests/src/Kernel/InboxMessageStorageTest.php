<?php

declare(strict_types=1);

namespace Drupal\Tests\commerce\Kernel;

use Drupal\commerce\InboxMessage;
use Drupal\commerce\InboxMessageStorageInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test inbox message storage service.
 *
 * @group commerce
 */
class InboxMessageStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce',
  ];

  /**
   * The inbox message storage.
   *
   * @var \Drupal\commerce\InboxMessageStorageInterface
   */
  protected InboxMessageStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('commerce', ['commerce_inbox_message']);
    $this->storage = $this->container->get('commerce.inbox_message_storage');
  }

  /**
   * Tests save operations on the inbox messages.
   */
  public function testSave() {
    $message_created = InboxMessage::fromArray([
      'id' => $this->randomMachineName(),
      'subject' => $this->randomString(),
      'message' => $this->randomString(),
      'cta_text' => $this->randomString(),
      'cta_link' => $this->randomString(),
      'send_date' => rand(),
      'state' => 'unread',
    ]);
    $this->storage->save($message_created);

    // Tests if saving the same item multiple times is possible.
    $this->storage->save($message_created);

    $messages = $this->storage->loadMultiple();
    $this->assertCount(1, $messages);
    $message_loaded = reset($messages);
    $this->assertInstanceOf(InboxMessage::class, $message_loaded);
    $this->assertEquals($message_created, $message_loaded);

    // Tests if resaving item with different state is possible.
    $message_created->state = 'dismissed';
    $this->storage->save($message_created);
    $messages = $this->storage->loadMultiple();
    $this->assertCount(1, $messages);
    $message_created->state = 'unread';
    $this->storage->save($message_created);
    $messages = $this->storage->loadMultiple();
    $this->assertCount(1, $messages);
  }

}

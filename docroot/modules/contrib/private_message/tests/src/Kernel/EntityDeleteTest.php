<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;
use Drupal\private_message\Entity\PrivateMessageBan;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Tests deletion of private message entities.
 *
 * @group private_message
 */
final class EntityDeleteTest extends KernelTestBase {

  use PrivateMessageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'message',
    'message_notify',
    'private_message',
    'private_message_notify',
    'system',
    'text',
    'user',
  ];

  /**
   * The thread.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageThreadInterface
   */
  protected PrivateMessageThreadInterface $thread;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installEntitySchema('private_message_ban');
    $this->installEntitySchema('private_message_thread');
    $this->installEntitySchema('private_message');
    $this->installSchema('private_message', ['pm_thread_history']);

    $this->installConfig([
      'filter',
      'message',
      'message_notify',
      'private_message_notify',
    ]);

    $this->createTestingUsers();
    $this->thread = \Drupal::getContainer()->get('private_message.service')->getThreadForMembers([
      $this->users['a'],
      $this->users['b'],
    ]);

    // Create a few messages.
    for ($i = 0; $i < 5; $i++) {
      $message = \Drupal::entityTypeManager()
        ->getStorage('private_message')
        ->create([
          'owner' => $this->users['a'],
          'message' => $this->randomString(),
        ]);
      $message->save();
      $this->thread->addMessage($message);
    }
  }

  /**
   * Test callback.
   */
  public function testDeleteThread(): void {
    $this->thread->delete();

    // Assert that the thread has been deleted.
    $this->assertNull(\Drupal::entityTypeManager()
      ->getStorage('private_message_thread')
      ->load($this->thread->id()));

    // Assert no messages are left.
    $this->assertEmpty(\Drupal::entityTypeManager()
      ->getStorage('private_message')
      ->loadMultiple());
  }

  /**
   * Tests that banned messages are also cleared.
   *
   * @dataProvider testBannedMessagesProvider
   */
  public function testBannedMessages(string $logged_in): void {
    $this->setCurrentUser($this->users[$logged_in]);
    $ban = PrivateMessageBan::create([
      'owner' => $this->users['b']->id(),
      'target' => $this->users['a']->id(),
    ]);
    $ban->save();
    $this->thread->delete();

    // Assert that the thread has been deleted.
    $this->assertNull(\Drupal::entityTypeManager()
      ->getStorage('private_message_thread')
      ->load($this->thread->id()));

    // Assert no messages are left.
    $this->assertEmpty(\Drupal::entityTypeManager()
      ->getStorage('private_message')
      ->loadMultiple());
  }

  /**
   * Provides test data for testBannedMessages().
   *
   * @return array[]
   *   The test data.
   */
  public static function testBannedMessagesProvider(): array {
    return [
      // User A is logged in, which is the user that created the messages.
      ['a'],
      // User B is logged in, they banned user A, they cannot see the messages.
      ['b'],
    ];
  }

  /**
   * Test deleting a thread using the storage class.
   */
  public function testStorageThreadAndMessageDeletion(): void {
    \Drupal::entityTypeManager()->getStorage('private_message_thread')->delete([$this->thread]);
    $this->assertNull(\Drupal::entityTypeManager()
      ->getStorage('private_message')
      ->load($this->thread->id()));
  }

}

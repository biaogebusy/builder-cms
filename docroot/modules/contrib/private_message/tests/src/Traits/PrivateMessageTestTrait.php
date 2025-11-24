<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Traits;

use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserInterface;

/**
 * Reusable test code.
 */
trait PrivateMessageTestTrait {

  use UserCreationTrait;

  /**
   * Testing users.
   *
   * @var array<string, \Drupal\user\UserInterface>
   */
  protected array $users = [];

  /**
   * Required permissions.
   *
   * @var array|string[]
   */
  protected array $requiredPermissions = [
    'use private messaging system',
    'access user profiles',
  ];

  /**
   * Update time.
   *
   * @var int
   */
  protected int $updateTime = 1704063635;

  /**
   * Creates testing users.
   *
   * @param int $amount
   *   (optional) Amount of users to be created. Defaults to 2.
   */
  protected function createTestingUsers(int $amount = 2): void {
    $keys = array_map(fn(int $i): string => chr($i + 96), range(1, $amount));
    $randomGenerator = $this->getRandomGenerator();

    foreach ($keys as $key) {
      $firstName = ucfirst($randomGenerator->word(rand(5, 9)));
      $lastName = ucfirst($randomGenerator->word(rand(5, 9)));
      $values = FieldConfig::loadByName(
        'user',
        'user',
        'full_name'
      ) ? ['full_name' => "$firstName $lastName"] : [];

      $this->users[$key] = $this->createUser($this->requiredPermissions, NULL, FALSE, $values);
    }
  }

  /**
   * Adds a full_name field to the user entity.
   */
  protected function attachFullNameField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'user',
      'type' => 'string',
      'field_name' => 'full_name',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'user',
      'bundle' => 'user',
      'field_name' => 'full_name',
    ])->save();
  }

  /**
   * Gets autocomplete label.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity.
   *
   * @return string
   *   Autocomplete label.
   */
  protected function getAutocompleteLabel(UserInterface $user): string {
    return $user->getDisplayName() . ' (' . $user->id() . ')';
  }

  /**
   * Creates thread with messages.
   *
   * @param \Drupal\user\UserInterface[] $members
   *   Members users.
   * @param \Drupal\user\UserInterface $sender
   *   Sender user.
   * @param int $numOfMessages
   *   Number of messages, range between 1 and 10.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThreadInterface
   *   Private message thread.
   */
  protected function createThreadWithMessages(array $members, UserInterface $sender, int $numOfMessages = 1): PrivateMessageThreadInterface {
    $numOfMessages = max(min(10, $numOfMessages), 1);

    $messages = [];
    for ($i = 1; $i <= $numOfMessages; $i++) {
      $this->updateTime += 3600;
      $messages[] = PrivateMessage::create([
        'owner' => $sender,
        'created' => $this->updateTime,
        'message' => [
          'value' => $this->getRandomGenerator()->sentences(5),
          'format' => 'plain_text',
        ],
      ]);
    }

    $thread = PrivateMessageThread::create([
      'members' => $members,
      'subject' => $this->getRandomGenerator()->word(10),
      'updated' => $this->updateTime,
      'private_messages' => $messages,
    ]);
    $thread->save();

    return $thread;
  }

}

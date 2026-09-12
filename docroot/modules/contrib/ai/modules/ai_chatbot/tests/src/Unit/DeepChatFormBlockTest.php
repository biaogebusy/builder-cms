<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Unit;

use Drupal\ai_chatbot\Plugin\Block\DeepChatFormBlock;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

/**
 * Tests avatar resolution in the DeepChat block.
 *
 * @group ai_chatbot
 */
class DeepChatFormBlockTest extends UnitTestCase {

  /**
   * Tests current-user tokens resolve without requiring use_avatar or Profile.
   */
  public function testDefaultAvatarUserTokenGetsUserContext(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn(7);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('getAccount')->willReturn($account);

    $userEntity = $this->createMock(UserInterface::class);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects($this->once())
      ->method('load')
      ->with(7)
      ->willReturn($userEntity);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($userStorage);
    $entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('profile')
      ->willReturn(FALSE);

    $token = $this->createMock(Token::class);
    $token->expects($this->once())
      ->method('replace')
      ->with(
        '[current-user:user_picture]',
        $this->callback(function (array $context) use ($userEntity): bool {
          return $context['user'] === $userEntity
            && $context['current-user'] === $userEntity
            && !isset($context['profile']);
        }),
        ['clear' => TRUE],
      )
      ->willReturn('https://example.com/user-picture.png');

    $block = $this->createBlock([
      'use_avatar' => FALSE,
      'default_avatar' => '[current-user:user_picture]',
    ], $currentUser, $entityTypeManager, $token);

    $this->assertSame([
      'username' => '',
      'avatar' => 'https://example.com/user-picture.png',
    ], $block->getUserData());
  }

  /**
   * Tests profile token resolution still includes the profile entity context.
   */
  public function testDefaultAvatarProfileTokenGetsProfileContext(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn(11);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('getAccount')->willReturn($account);

    $userEntity = $this->createMock(UserInterface::class);
    $profile = (object) ['id' => 'profile-1'];

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects($this->once())
      ->method('load')
      ->with(11)
      ->willReturn($userEntity);

    $profileStorage = $this->createMock(EntityStorageInterface::class);
    $profileStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(['uid' => 11, 'type' => 'admin_profile'])
      ->willReturn([$profile]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturnMap([
        ['user', $userStorage],
        ['profile', $profileStorage],
      ]);
    $entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('profile')
      ->willReturn(TRUE);

    $token = $this->createMock(Token::class);
    $token->expects($this->once())
      ->method('replace')
      ->with(
        '[current-user:admin_profile:field_profile_image]',
        $this->callback(function (array $context) use ($userEntity, $profile): bool {
          return $context['user'] === $userEntity
            && $context['current-user'] === $userEntity
            && $context['profile'] === $profile;
        }),
        ['clear' => TRUE],
      )
      ->willReturn('https://example.com/profile.png');

    $block = $this->createBlock([
      'use_avatar' => FALSE,
      'default_avatar' => '[current-user:admin_profile:field_profile_image]',
    ], $currentUser, $entityTypeManager, $token);

    $this->assertSame([
      'username' => '',
      'avatar' => 'https://example.com/profile.png',
    ], $block->getUserData());
  }

  /**
   * Tests non-profile tokens do not trigger profile lookups.
   */
  public function testNonProfileTokensDoNotTriggerProfileLookup(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn(13);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('getAccount')->willReturn($account);

    $userEntity = $this->createMock(UserInterface::class);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects($this->once())
      ->method('load')
      ->with(13)
      ->willReturn($userEntity);

    $profileStorage = $this->createMock(EntityStorageInterface::class);
    $profileStorage->expects($this->never())
      ->method('loadByProperties');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->willReturnMap([
        ['user', $userStorage],
        ['profile', $profileStorage],
      ]);
    $entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('profile')
      ->willReturn(TRUE);

    $token = $this->createMock(Token::class);
    $token->expects($this->once())
      ->method('replace')
      ->with(
        '[site:name]',
        $this->callback(function (array $context) use ($userEntity): bool {
          return $context['user'] === $userEntity
            && $context['current-user'] === $userEntity
            && array_key_exists('profile', $context)
            && $context['profile'] === NULL;
        }),
        ['clear' => TRUE],
      )
      ->willReturn('https://example.com/site-avatar.png');

    $block = $this->createBlock([
      'use_avatar' => FALSE,
      'default_avatar' => '[site:name]',
    ], $currentUser, $entityTypeManager, $token);

    $this->assertSame([
      'username' => '',
      'avatar' => 'https://example.com/site-avatar.png',
    ], $block->getUserData());
  }

  /**
   * Creates a block instance with the required mocked services.
   */
  private function createBlock(
    array $configuration,
    AccountProxyInterface $currentUser,
    EntityTypeManagerInterface $entityTypeManager,
    Token $token,
  ): DeepChatFormBlock {
    $block = new DeepChatFormBlock($configuration + [
      'default_username' => '',
      'use_username' => FALSE,
      'use_avatar' => FALSE,
      'default_avatar' => '',
    ], 'ai_deepchat_block', [
      'provider' => 'ai_chatbot',
      'admin_label' => 'AI DeepChat Chatbot',
    ]);

    $this->setProtectedProperty($block, 'currentUser', $currentUser);
    $this->setProtectedProperty($block, 'entityTypeManager', $entityTypeManager);
    $this->setProtectedProperty($block, 'token', $token);

    return $block;
  }

  /**
   * Sets a protected property on the given object.
   */
  private function setProtectedProperty(object $object, string $property, mixed $value): void {
    $reflection = new \ReflectionProperty($object, $property);
    $reflection->setValue($object, $value);
  }

}

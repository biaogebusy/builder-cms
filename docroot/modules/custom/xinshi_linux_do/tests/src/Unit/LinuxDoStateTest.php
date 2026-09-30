<?php

namespace Drupal\Tests\xinshi_linux_do\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_linux_do\LinuxDoSDK;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Exercises cross-browser binding and one-time state consumption.
 */
final class LinuxDoStateTest extends TestCase {

  private array $records = [];
  private int $now = 1000;
  private bool $lockAvailable = TRUE;
  private RequestStack $requests;
  private LinuxDoSDK $sdk;
  private EntityTypeManagerInterface $entities;

  protected function setUp(): void {
    $this->requests = new RequestStack();
    $this->browser();
    $store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $store->method('setWithExpire')->willReturnCallback(function ($key, $value) {
      $this->records[$key] = $value;
    });
    $store->method('get')->willReturnCallback(fn($key) => $this->records[$key] ?? NULL);
    $store->method('delete')->willReturnCallback(function ($key) {
      unset($this->records[$key]);
    });
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->method('get')->willReturn($store);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(fn() => $this->lockAvailable);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $http = $this->createMock(ClientInterface::class);
    $http->expects($this->never())->method('request');
    $this->entities = $this->createMock(EntityTypeManagerInterface::class);
    $this->sdk = new LinuxDoSDK(
      $this->createMock(ConfigFactoryInterface::class),
      $this->entities, $http, $logger,
      $this->createMock(FileRepositoryInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->requests, $factory, $lock, $time,
    );
  }

  private function browser(): void {
    $request = Request::create('/user/login/linux_do');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->requests->push($request);
  }

  public function testOtherBrowserCannotConsumeState(): void {
    $state = $this->sdk->startState('/oauth/authorize?client_id=test');
    $this->browser();
    $this->assertFalse($this->sdk->consumeState($state));
    $this->requests->pop();
    $this->assertTrue($this->sdk->consumeState($state));
  }

  public function testStateAndDestinationAreSingleUse(): void {
    $state = $this->sdk->startState('/oauth/authorize?client_id=test');
    $this->assertTrue($this->sdk->consumeState($state));
    $this->assertSame('/oauth/authorize?client_id=test', $this->sdk->consumeDestination());
    $this->assertNull($this->sdk->consumeDestination());
    $this->assertFalse($this->sdk->consumeState($state));
    $other_instance = clone $this->sdk;
    $this->assertFalse($other_instance->consumeState($state));
  }

  public function testConcurrentCallbackCannotConsumeState(): void {
    $state = $this->sdk->startState();
    $this->lockAvailable = FALSE;
    $this->assertFalse($this->sdk->consumeState($state));
    $this->lockAvailable = TRUE;
    $this->assertTrue($this->sdk->consumeState($state));
  }

  public function testExpiredAndFutureStateAreRejected(): void {
    $state = $this->sdk->startState();
    $this->now += LinuxDoSDK::STATE_TTL;
    $this->assertFalse($this->sdk->consumeState($state));
    $state = $this->sdk->startState();
    $this->now--;
    $this->assertFalse($this->sdk->consumeState($state));
  }

  public function testMalformedStateDoesNotConsumeValidState(): void {
    $state = $this->sdk->startState();
    foreach (['', 'fake', str_repeat('x', 5000), $state . 'x'] as $invalid) {
      $this->assertFalse($this->sdk->consumeState($invalid));
    }
    $this->assertTrue($this->sdk->consumeState($state));
  }

  public function testFreshLoginAndMultipleTabsHaveIndependentChallenges(): void {
    $first = $this->sdk->startState();
    $second = $this->sdk->startState();
    $this->assertNotSame($first, $second);
    $this->assertTrue($this->sdk->consumeState($second));
    $this->assertTrue($this->sdk->consumeState($first));
    $this->assertFalse($this->sdk->consumeState($second));
  }

  public function testMissingSessionFailsClosed(): void {
    $state = $this->sdk->startState();
    $this->requests->push(Request::create('/user/login/linux_do/callback'));
    $this->assertFalse($this->sdk->consumeState($state));
  }

  public function testBlockedAccountMappingDoesNotModifyProfileOrDownloadAvatar(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(FALSE);
    $account->expects($this->never())->method('set');
    $account->expects($this->never())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    // Exercise lookup by external ID, then fallback lookup by email.
    $storage->method('loadByProperties')->willReturnOnConsecutiveCalls([$account], [], [$account]);
    $this->entities->method('getStorage')->with('user')->willReturn($storage);
    $profile = ['id' => 1, 'email' => 'test@example.invalid', 'username' => 'test', 'avatar_template' => 'https://example.invalid/avatar'];
    $this->assertSame([NULL, 'account_blocked'], $this->sdk->mapProfileToUser($profile));
    $this->assertSame([NULL, 'account_blocked'], $this->sdk->mapProfileToUser($profile));
  }

}

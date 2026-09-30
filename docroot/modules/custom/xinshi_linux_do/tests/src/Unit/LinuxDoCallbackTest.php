<?php

namespace Drupal\Tests\xinshi_linux_do\Unit;

use Drupal\user\UserInterface;
use Drupal\xinshi_linux_do\Controller\LinuxDoAuthController;
use Drupal\xinshi_linux_do\LinuxDoSDK;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Checks callback ordering before external requests and session establishment.
 */
final class LinuxDoCallbackTest extends TestCase {

  protected function setUp(): void {
    $GLOBALS['xinshi_linux_do_test_logins'] = [];
  }

  public static function cancelledCallbacks(): array {
    return [['?state=test&error=access_denied'], ['?state=test']];
  }

  #[DataProvider('cancelledCallbacks')]
  public function testCancellationConsumesStateWithoutExchangingCode(string $query): void {
    $sdk = $this->createMock(LinuxDoSDK::class);
    $sdk->expects($this->once())->method('consumeState')->with('test')->willReturn(TRUE);
    $sdk->expects($this->never())->method('exchangeCodeForToken');
    $this->expectException(BadRequestHttpException::class);
    (new LinuxDoAuthController($sdk))->callback(Request::create('/callback' . $query));
  }

  public function testInvalidStateCannotReachProvider(): void {
    $sdk = $this->createMock(LinuxDoSDK::class);
    $sdk->method('consumeState')->willReturn(FALSE);
    $sdk->expects($this->never())->method('exchangeCodeForToken');
    $this->expectException(BadRequestHttpException::class);
    (new LinuxDoAuthController($sdk))->callback(Request::create('/callback?state=test&code=test'));
  }

  public static function accountStates(): array {
    return [[TRUE], [FALSE]];
  }

  #[DataProvider('accountStates')]
  public function testOnlyActiveAccountCanEstablishSession(bool $active): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('isActive')->willReturn($active);
    $sdk = $this->createMock(LinuxDoSDK::class);
    $sdk->method('consumeState')->willReturn(TRUE);
    $sdk->method('exchangeCodeForToken')->willReturn(['access_token' => 'test-only']);
    $sdk->method('fetchProfile')->willReturn(['id' => 1]);
    $sdk->method('mapProfileToUser')->willReturn([$account, NULL]);
    $sdk->method('consumeDestination')->willReturn('/oauth/authorize?client_id=test');
    $request = Request::create('/callback?state=test&code=test');
    $request->setSession(new Session(new MockArraySessionStorage()));
    try {
      $response = (new LinuxDoAuthController($sdk))->callback($request);
      $this->assertTrue($active);
      $this->assertSame('/oauth/authorize?client_id=test', $response->getTargetUrl());
    }
    catch (BadRequestHttpException) {
      $this->assertFalse($active);
    }
    $this->assertSame($active ? [7] : [], $GLOBALS['xinshi_linux_do_test_logins']);
    $this->assertSame($active, $request->getSession()->get(LinuxDoSDK::SESSION_FEDERATED_KEY, FALSE));
  }

}

namespace Drupal\xinshi_linux_do\Controller;

/**
 * Records session establishment without logging in to a real site.
 */
function user_login_finalize($account): void {
  $GLOBALS['xinshi_linux_do_test_logins'][] = $account->id();
}


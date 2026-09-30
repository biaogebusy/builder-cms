<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_sms\Otp;
use Drupal\xinshi_sms\Exception\OtpRateLimitException;
use Drupal\xinshi_sms\Plugin\rest\resource\OtpSubmitResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies that rejected credentials cannot reach session or token creation.
 */
final class OtpSubmitResourceTest extends TestCase {

  private ContainerBuilder $container;
  private RequestStack $requests;
  private Otp $otp;
  private TestOtpSubmitResource $resource;

  protected function setUp(): void {
    $this->container = new ContainerBuilder();
    $this->container->set('current_user', $this->createMock(AccountProxyInterface::class));
    $this->otp = $this->createMock(Otp::class);
    $this->container->set('xinshi_sms.OTP', $this->otp);
    \Drupal::setContainer($this->container);
    $this->requests = new RequestStack();
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('moduleExists')->willReturn(TRUE);
    $this->resource = new TestOtpSubmitResource([], 'xinshi_otp_login', [], ['json'], new NullLogger());
    $this->resource->initialize($this->requests, $modules);
  }

  public static function malformedBodies(): array {
    return array_map(fn($body) => [$body], ['null', 'true', '[]', '"text"', '{', '{"code":true}', '{"code":123456}', '{"code":[]}']);
  }

  #[DataProvider('malformedBodies')]
  public function testInvalidInputCannotReachAuthentication(string $body): void {
    $this->requests->push(Request::create('/api/v3/otp/login', 'POST', [], [], [], [], $body));
    $this->otp->expects($this->never())->method('validateOtp');
    $this->otp->expects($this->never())->method('userOtpLogin');
    $response = $this->resource->post();
    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('code_incorrect', $response->getResponseData()['code']);
    $this->assertFalse($this->resource->issued);
  }

  public static function loginModes(): array {
    return [[''], ['oauth2']];
  }

  #[DataProvider('loginModes')]
  public function testRejectedOtpCannotCreateSessionOrToken(string $grant): void {
    $this->request($grant);
    $this->otp->method('validateMobileNumber')->willReturn('');
    $this->otp->method('validateOtp')->willReturn(TRUE);
    $this->otp->expects($this->never())->method('userOtpLogin');
    $response = $this->resource->post();
    $this->assertSame(401, $response->getStatusCode());
    $this->assertFalse($response->getResponseData()['status']);
    $this->assertFalse($this->resource->issued);
  }

  public function testValidCodeCanReachOAuthIssuanceWithIntegerPhone(): void {
    $this->request('oauth2');
    $this->otp->method('validateMobileNumber')->willReturn('');
    $this->otp->expects($this->once())->method('validateOtp')->with('123456', 13800000000)->willReturn(FALSE);
    $this->otp->expects($this->never())->method('userOtpLogin');
    $response = $this->resource->post();
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('test-only', $response->getResponseData()['access_token']);
  }

  #[DataProvider('loginModes')]
  public function testThrottledVerificationCannotCreateSessionOrToken(string $grant): void {
    $this->request($grant);
    $this->otp->method('validateMobileNumber')->willReturn('');
    $this->otp->method('validateOtp')->willThrowException(new OtpRateLimitException(300));
    $this->otp->expects($this->never())->method('userOtpLogin');
    $response = $this->resource->post();
    $this->assertSame(429, $response->getStatusCode());
    $this->assertSame('code_verify_throttled', $response->getResponseData()['code']);
    $this->assertSame('300', $response->headers->get('Retry-After'));
    $this->assertFalse($this->resource->issued);
  }

  public function testLegacyResourcePreservesOtpInputName(): void {
    $resource = new TestOtpSubmitResource([], 'submit_otp_resource', [], ['json'], new NullLogger());
    $resource->initialize($this->requests, $this->createMock(ModuleHandlerInterface::class));
    $this->requests->push(Request::create('/otp/login', 'POST', [], [], [], [], json_encode([
      'mobile_number' => '13800000000', 'otp' => '123456', 'grant_type' => 'oauth2',
    ])));
    $this->otp->method('validateMobileNumber')->willReturn('');
    $this->otp->expects($this->once())->method('validateOtp')->with('123456', '13800000000')->willReturn(FALSE);
    $this->assertSame(200, $resource->post()->getStatusCode());
    $this->assertTrue($resource->issued);
  }

  public function testAccountBlockedBetweenValidationAndSessionCreationIsRejected(): void {
    $this->request('');
    $this->otp->method('validateMobileNumber')->willReturn('');
    $this->otp->method('validateOtp')->willReturn(FALSE);
    $this->otp->method('userOtpLogin')->willReturn(FALSE);
    $response = $this->resource->post();
    $this->assertSame(401, $response->getStatusCode());
    $this->assertArrayNotHasKey('current_user', $response->getResponseData());
  }

  public function testActualOAuthIssuerRechecksBlockedAccountBeforeTokenWrites(): void {
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(FALSE);
    $this->otp->method('otpLoginCheckUserAlreadyExists')->willReturn($user);
    $this->container->set('simple_oauth.repositories.client', new class {
      public function getClientEntity($id): object {
        return new \stdClass();
      }
    });
    // These intentionally have no methods: any token operation would fail.
    foreach (['scope', 'access_token', 'refresh_token'] as $repository) {
      $this->container->set('simple_oauth.repositories.' . $repository, new \stdClass());
    }
    $this->container->set('config.factory', new \stdClass());
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No active user');
    $this->resource->actualIssuer(['client_id' => 'test', 'mobile_number' => '13800000000']);
  }

  private function request(string $grant): void {
    $this->requests->push(Request::create('/api/v3/otp/login', 'POST', [], [], [], [], json_encode([
      'mobile_number' => 13800000000, 'code' => '123456', 'grant_type' => $grant, 'client_id' => 'test',
    ])));
  }

}

/**
 * Substitutes only successful signing; the blocked-issuer test calls the parent.
 */
final class TestOtpSubmitResource extends OtpSubmitResource {

  public bool $issued = FALSE;

  public function initialize(RequestStack $requests, ModuleHandlerInterface $modules): void {
    $this->request = $requests;
    $this->moduleHandler = $modules;
  }

  protected function generateOAuthToken(array $user_input) {
    $this->issued = TRUE;
    return ['access_token' => 'test-only'];
  }

  public function actualIssuer(array $input): array {
    return parent::generateOAuthToken($input);
  }

}

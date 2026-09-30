<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_sms\Exception\OtpRateLimitException;
use Drupal\xinshi_sms\Otp;
use Drupal\xinshi_sms\Plugin\rest\resource\OtpGenerateResource;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * REST maps the service limit and does not maintain a second send counter.
 */
final class OtpGenerateResourceTest extends TestCase {

  public function testSendLimitReturns429AndRetryAfter(): void {
    $container = new ContainerBuilder();
    $container->set('current_user', $this->createMock(AccountProxyInterface::class));
    $otp = $this->createMock(Otp::class);
    $otp->method('validateMobileNumber')->willReturn('');
    $otp->expects($this->once())->method('generateOtp')->with('13800000000')->willThrowException(new OtpRateLimitException(3600));
    $container->set('xinshi_sms.OTP', $otp);
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('executeInRenderContext')->willReturnCallback(fn($context, $callback) => $callback());
    $container->set('renderer', $renderer);
    \Drupal::setContainer($container);
    $requests = new RequestStack();
    $requests->push(Request::create('/api/v3/otp/generate', 'POST', [], [], [], [], '{"mobile_number":"13800000000"}'));
    $resource = new class([], 'xinshi_otp_generate', [], ['json'], new NullLogger()) extends OtpGenerateResource {
      public function initialize(RequestStack $requests): void { $this->request = $requests; }
    };
    $resource->initialize($requests);
    $response = $resource->post();
    $this->assertSame(429, $response->getStatusCode());
    $this->assertSame('code_send_throttled', $response->getResponseData()['code']);
    $this->assertSame('3600', $response->headers->get('Retry-After'));
  }

}

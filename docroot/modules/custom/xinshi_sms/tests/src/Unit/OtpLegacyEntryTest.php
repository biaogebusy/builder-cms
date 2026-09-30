<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\xinshi_sms\Routing\RouteSubscriber;
use Drupal\xinshi_sms\XinshiSmsServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Legacy dependency entry points cannot select an unprotected OTP service.
 */
final class OtpLegacyEntryTest extends TestCase {

  public function testLegacyServiceResolvesToTheSharedInstance(): void {
    $container = new ContainerBuilder();
    $shared = new \stdClass();
    $container->set('xinshi_sms.OTP', $shared);
    $container->register('otp_login.OTP', \Drupal\otp_login\Otp::class);
    (new XinshiSmsServiceProvider())->alter($container);
    $this->assertSame($shared, $container->get('otp_login.OTP'));
  }

  public function testLegacyFormUsesTheProtectedFormClass(): void {
    $routes = new RouteCollection();
    $routes->add('otp_login.otp_login_form', new Route('/otp/login'));
    $subscriber = new class extends RouteSubscriber {
      public function alter(RouteCollection $routes): void { $this->alterRoutes($routes); }
    };
    $subscriber->alter($routes);
    $this->assertSame('\Drupal\xinshi_sms\Form\OtpLoginForm', $routes->get('otp_login.otp_login_form')->getDefault('_form'));
  }

  public function testLegacyRestDefinitionsKeepPathsAndUseProtectedHandlers(): void {
    require_once dirname(__DIR__, 3) . '/xinshi_sms.module';
    $definitions = [
      'generate_otp_resource' => ['class' => 'old', 'uri_paths' => ['canonical' => '/otp/generate']],
      'submit_otp_resource' => ['class' => 'old', 'uri_paths' => ['canonical' => '/otp/login']],
      'unrelated' => ['class' => 'unchanged'],
    ];
    xinshi_sms_rest_resource_alter($definitions);
    $this->assertSame(\Drupal\xinshi_sms\Plugin\rest\resource\OtpGenerateResource::class, $definitions['generate_otp_resource']['class']);
    $this->assertSame(\Drupal\xinshi_sms\Plugin\rest\resource\OtpSubmitResource::class, $definitions['submit_otp_resource']['class']);
    $this->assertSame('/otp/login', $definitions['submit_otp_resource']['uri_paths']['canonical']);
    $this->assertSame('unchanged', $definitions['unrelated']['class']);
  }

}

<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\xinshi_sms\Exception\OtpRateLimitException;
use Drupal\xinshi_sms\Form\BindPhoneForm;
use Drupal\xinshi_sms\Form\FindPasswordForm;
use Drupal\xinshi_sms\Form\OtpLoginForm;
use Drupal\xinshi_sms\Otp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Throttled forms must not claim a send succeeded or reach account mutations.
 */
final class OtpFormRateLimitTest extends TestCase {

  private Otp $otp;
  private AccountProxyInterface $account;
  private MessengerInterface $messenger;

  protected function setUp(): void {
    $container = new ContainerBuilder();
    $this->account = $this->createMock(AccountProxyInterface::class);
    $this->account->method('id')->willReturn(7);
    $container->set('current_user', $this->account);
    $this->otp = $this->createMock(Otp::class);
    foreach (['generateOtp', 'sendVerificationCode', 'validateOtp', 'validateOtpByUser'] as $method) {
      $this->otp->method($method)->willThrowException(new OtpRateLimitException(300));
    }
    $this->otp->expects($this->never())->method('userOtpLogin');
    $container->set('xinshi_sms.OTP', $this->otp);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(fn($value) => $value->getUntranslatedString());
    $container->set('string_translation', $translation);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $container->set('messenger', $this->messenger);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(FALSE);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    $container->set('config.factory', $factory);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $storage->expects($this->never())->method('load');
    $storage->expects($this->never())->method('create');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturn($storage);
    $container->set('entity_type.manager', $entities);
    \Drupal::setContainer($container);
  }

  public static function ajaxCases(): array {
    return [
      ['login', 'otpLoginGenerateOtpCallback', 'code requests'],
      ['password', 'sendAjax', 'code requests'],
      ['bind', 'obtainCodeCallback', 'code requests'],
      ['bind', 'bindMobilePhoneCallback', 'verification attempts'],
    ];
  }

  #[DataProvider('ajaxCases')]
  public function testAjaxLimitShowsErrorWithoutCountdownOrSuccess(string $type, string $method, string $message): void {
    $object = $this->form($type);
    $form = [];
    $state = $this->state();
    $commands = $object->$method($form, $state)->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('invoke', $commands[0]['command']);
    $this->assertSame('text', $commands[0]['method']);
    $this->assertStringContainsString($message, (string) $commands[0]['args'][0]);
  }

  public static function submitCases(): array {
    return [['login', 'submitForm'], ['password', 'nextSubmit'], ['password', 'submitForm']];
  }

  #[DataProvider('submitCases')]
  public function testSubmitLimitRebuildsWithoutAdvancingOrWriting(string $type, string $method): void {
    $object = $this->form($type);
    $form = [];
    $state = $this->state();
    $this->messenger->expects($this->once())->method('addError');
    $object->$method($form, $state);
    $this->assertTrue($state->isRebuilding());
    $this->assertEmpty($state->getRedirect());
    if ($method === 'nextSubmit') {
      $this->assertSame(1, $state->getValue('step'));
    }
  }

  private function form(string $type): object {
    return match ($type) {
      'login' => new OtpLoginForm($this->otp),
      'password' => new FindPasswordForm($this->otp, $this->account),
      'bind' => new BindPhoneForm(),
    };
  }

  private function state(): FormState {
    return (new FormState())->setValues(['mobile_number' => '13800000000', 'code' => '123456', 'step' => 1]);
  }

}

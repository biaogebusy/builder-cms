<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\sms\Message\SmsMessage;
use Drupal\Component\Utility\Html;

/**
 * Tests SMS Framework tokens.
 *
 * @group SMS Framework
 */
final class SmsFrameworkTokenTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'sms', 'entity_test', 'user', 'field', 'telephone',
    'dynamic_entity_reference',
  ];

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  private Token $tokenService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenService = $this->container->get('token');
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests 'sms' tokens.
   */
  public function testSmsTokens(): void {
    $url_options = ['absolute' => TRUE];
    static::assertEquals(
      Url::fromRoute('sms.phone.verify', [], $url_options)->toString(),
      $this->tokenService->replace('[sms:verification-url]'),
    );
  }

  /**
   * Tests verification code token.
   *
   * Special case token.
   */
  public function testVerificationCode(): void {
    $data['sms_verification_code'] = $this->randomMachineName();
    static::assertEquals(
      $data['sms_verification_code'],
      $this->tokenService->replace('[sms-message:verification-code]', $data),
    );
  }

  /**
   * Tests 'sms-message' tokens.
   */
  public function testSmsMessageTokens(): void {
    $phone_numbers = ['+123123123', '+456456456'];
    $message = $this->randomString();
    $sms_message = new SmsMessage();
    $sms_message
      ->setSenderNumber('+999888777')
      ->setMessage($message)
      ->addRecipients($phone_numbers);
    $data['sms-message'] = $sms_message;

    static::assertEquals(
      $phone_numbers[0],
      $this->tokenService->replace('[sms-message:phone-number]', $data),
    );

    static::assertEquals(
      Html::escape($message),
      $this->tokenService->replace('[sms-message:message]', $data),
    );
  }

}

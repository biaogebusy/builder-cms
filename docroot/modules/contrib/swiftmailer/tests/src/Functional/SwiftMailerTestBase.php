<?php

namespace Drupal\Tests\swiftmailer\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\swiftmailer_test\SwiftMailerDrupalStateLogger;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for swiftmailer web tests.
 */
abstract class SwiftMailerTestBase extends BrowserTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['swiftmailer_test', 'swiftmailer', 'mailsystem'];

  /**
   * @var \Drupal\swiftmailer_test\SwiftMailerDrupalStateLogger
   */
  protected $logger = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('mailsystem.settings')
      ->set('defaults', [
        'formatter' => 'swiftmailer',
        'sender' => 'test_mail_collector',
      ])
      ->clear('modules.swiftmailer.none')
      ->save();
    $this->logger = new SwiftMailerDrupalStateLogger();
  }

  /**
   * Checks that the most recently sent email contains text.
   *
   * @param string $value
   *   Text to check for.
   */
  protected function assertBodyContains($value) {
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $email = end($captured_emails);
    $this->assertStringContainsString($value, (string) $email['body']);
  }

  /**
   * Checks the subject of the most recently sent email.
   *
   * @param string $value
   *   Text to check for.
   */
  protected function assertSubject($value) {
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $email = end($captured_emails);
    $this->assertEquals($value, (string) $email['subject']);
  }

  /**
   * Enables Plain text emails.
   */
  protected function enablePlain() {
    $this->config('swiftmailer.message')
      ->set('content_type', SWIFTMAILER_FORMAT_PLAIN)
      ->save();
  }

}

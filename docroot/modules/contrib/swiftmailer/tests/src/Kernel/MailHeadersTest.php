<?php

namespace Drupal\Tests\swiftmailer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\swiftmailer_test\SwiftMailerDrupalStateLogger;

/**
 * Tests the headers of an email sent by swiftmailer.
 *
 * @group swiftmailer
 */
class MailHeadersTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mailsystem',
    'swiftmailer',
    'swiftmailer_test',
    'system',
    'filter',
  ];

  /**
   * Swift mailer state logger.
   *
   * @var \Drupal\swiftmailer_test\SwiftMailerDrupalStateLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['swiftmailer', 'filter']);

    $this->config('system.site')->set('mail', 'site@example.com')->save();

    $this->config('mailsystem.settings')
      ->set('modules.swiftmailer_test.none', [
        'formatter' => 'swiftmailer',
        'sender' => 'swiftmailer',
      ])->save();

    $this->config('swiftmailer.transport')
      ->set('transport', 'null')
      ->save();

    // Install the test theme for a simple template.
    \Drupal::service('theme_installer')->install(['swiftmailer_test_theme']);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'swiftmailer_test_theme')
      ->save();

    \Drupal::configFactory()->getEditable('mailsystem.settings')->set('theme', 'swiftmailer_test_theme')->save();

    $this->logger = new SwiftMailerDrupalStateLogger();
  }

  /**
   * Tests headers in the emails sent.
   */
  public function testHeaders() {
    $module = 'swiftmailer_test';
    $key = 'headers_test';
    $to = 'test@example.com';
    $langcode = $this->container->get('language_manager')->getDefaultLanguage()->getId();
    $mail_plugin_manager = $this->container->get('plugin.manager.mail');

    foreach ($this->getTestCases() as $test_case) {
      $params = ['headers' => $test_case['headers']];

      $mail_plugin_manager->mail($module, $key, $to, $langcode, $params);

      $dump = $this->logger->dump();
      $actual = $dump[0]['headers'][$test_case['header_name']] ?? '';
      $this->assertEquals($test_case['expected'], $actual);

      // Cleanup for the next test case.
      $this->logger->clear();
    }
  }

  /**
   * Provides test cases for ::testHeaders().
   *
   * @return array
   *   An array of test cases.
   */
  protected function getTestCases() {
    return [
      'Cc valid header' => [
        'header_name' => 'Cc',
        'headers' => [
          'Cc' => 'test1@example.com;test2@example.com, "Test user" <test3@example.com>',
        ],
        'expected' => 'test1@example.com, test2@example.com, Test user <test3@example.com>',
      ],
      'Cc invalid header' => [
        'header_name' => 'Cc',
        'headers' => [
          'Cc' => 'test1@example.com-test2@example.com, test3@example.com test4@example.com',
        ],
        'expected' => '',
      ],
      'Bcc valid header' => [
        'header_name' => 'Bcc',
        'headers' => [
          'Bcc' => 'test1@example.com;test2@example.com, "Test user" <test3@example.com>',
        ],
        'expected' => 'test1@example.com, test2@example.com, Test user <test3@example.com>',
      ],
      'Bcc invalid header' => [
        'header_name' => 'Bcc',
        'headers' => [
          'Bcc' => 'test1@example.com-test2@example.com, test3@example.com test4@example.com',
        ],
        'expected' => '',
      ],
    ];
  }

}

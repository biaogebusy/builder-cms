<?php

namespace Drupal\Tests\swiftmailer\Functional;

use Drupal\Core\Render\Markup;
use Drupal\swiftmailer\Plugin\Mail\SwiftMailer;
use Drupal\swiftmailer_test\SwiftMailerDrupalStateLogger;

/**
 * @group swiftmailer
 */
class SwiftMailerAlterTest extends SwiftMailerTestBase {

  protected function setUp(): void {
    parent::setUp();
    $this->config('mailsystem.settings')
      ->set('modules.swiftmailer_test.none', [
        'formatter' => 'swiftmailer',
        'sender' => 'swiftmailer',
      ])
      ->save();
    $this->config('swiftmailer.transport')
      ->set('transport', 'null')
      ->save();
    $this->logger = new SwiftMailerDrupalStateLogger();
  }

  public function testAlter() {
    \Drupal::state()->set('swiftmailer_test_swiftmailer_alter_1', TRUE);
    \Drupal::service('plugin.manager.mail')->mail('swiftmailer_test', 'test_1', 'test@example.com', \Drupal::languageManager()->getDefaultLanguage()->getId());
    $this->assertEquals('Replace text in swiftmailer_test_swiftmailer_alter', $this->logger->dump()[0]['body']);
  }

  public function testTemplatePreprocess() {
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'swiftmailer_test_theme')
      ->save();

    \Drupal::configFactory()
      ->getEditable('mailsystem.settings')
      ->set('theme', 'default')
      ->save();

    \Drupal::service('theme_installer')->install(['swiftmailer_test_theme']);

    $params = [
      'content_type' => SWIFTMAILER_FORMAT_HTML,
    ];

    \Drupal::service('plugin.manager.mail')->mail('swiftmailer_test', 'test-2', 'test@example.com', \Drupal::languageManager()->getDefaultLanguage()->getId(), $params);
    $this->assertStringContainsString('string_from_template', (string) $this->logger->dump()[0]['body']);
    $this->assertStringContainsString('variable_from_preprocess', (string) $this->logger->dump()[0]['body']);
  }

  /**
   * Create plain text version from body.
   */
  public function testGeneratePlainTextVersion() {
    $plugin = SwiftMailer::create(\Drupal::getContainer(), [], NULL, NULL);

    $message = [
      'module' => 'swiftmailer_test',
      'key' => 'swiftmailer_test_1',
      'headers' => [
        'Content-Type' => SWIFTMAILER_FORMAT_HTML,
      ],
      'params' => [
        'generate_plain' => TRUE,
      ],
      'subject' => 'Subject',
      'body' => [
        Markup::create('<strong>Hello World</strong>')
      ]
    ];

    $message = $plugin->format($message);
    $this->assertStringContainsString('<strong>Hello World</strong>', (string) $message['body']);
    $this->assertEquals('HELLO WORLD', $message['plain']);
  }

  /**
   * Preserve original plain text, do not generate it from body.
   */
  public function testKeepOriginalPlainTextVersion() {
    $plugin = SwiftMailer::create(\Drupal::getContainer(), [], NULL, NULL);

    $message = [
      'module' => 'swiftmailer_test',
      'key' => 'swiftmailer_test_1',
      'headers' => [
        'Content-Type' => SWIFTMAILER_FORMAT_HTML,
      ],
      'params' => [
        'generate_plain' => FALSE,
      ],
      'subject' => 'Subject',
      'plain' => 'Original Plain Text Version',
      'body' => [
        Markup::create('<strong>Hello World</strong>')
      ]
    ];

    $message = $plugin->format($message);
    $this->assertStringContainsString('<strong>Hello World</strong>', (string) $message['body']);
    $this->assertEquals('Original Plain Text Version', $message['plain']);
  }

  public function testPlainTextConfigurationSetting() {
    $this->config('swiftmailer.message')
      ->set('content_type', SWIFTMAILER_FORMAT_HTML)
      ->set('generate_plain', TRUE)
      ->save();

    $plugin = SwiftMailer::create(\Drupal::getContainer(), [], NULL, NULL);

    // Empty plain text, generate from html.
    $message = [
      'module' => 'swiftmailer_test',
      'key' => 'swiftmailer_test_1',
      'subject' => 'Subject',
      'body' => [
        Markup::create('<strong>Hello World</strong>')
      ]
    ];

    $message = $plugin->format($message);
    $this->assertStringContainsString('<strong>Hello World</strong>', (string) $message['body']);
    $this->assertEquals('HELLO WORLD', $message['plain']);

    // Keep original plain text version.
    $message = [
      'module' => 'swiftmailer_test',
      'key' => 'swiftmailer_test_1',
      'subject' => 'Subject',
      'plain' => 'Original Plain Text Version',
      'body' => [
        Markup::create('<strong>Hello World</strong>')
      ]
    ];

    $message = $plugin->format($message);
    $this->assertStringContainsString('<strong>Hello World</strong>', (string) $message['body']);
    $this->assertEquals('Original Plain Text Version', $message['plain']);
  }

}

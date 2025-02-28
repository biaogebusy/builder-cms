<?php

namespace Drupal\Tests\swiftmailer\Kernel\Plugin\Mail;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Markup;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\swiftmailer\Plugin\Mail\SwiftMailer
 * @group swiftmailer
 */
class FormatTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'filter',
    'swiftmailer',
    'system',
  ];

  /**
   * The swiftmailer plugin.
   *
   * @var \Drupal\swiftmailer\Plugin\Mail\SwiftMailer
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'swiftmailer',
      'filter',
    ]);
    $this->installEntitySchema('user');
    $this->installSchema('user', 'users_data');
    $this->plugin = $this->container->get('plugin.manager.mail')
      ->createInstance('swiftmailer');

    // Install the test theme for a simple template.
    \Drupal::service('theme_installer')->install(['swiftmailer_test_theme']);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'swiftmailer_test_theme')
      ->save();
  }

  /**
   * Tests formatting the message.
   *
   * @dataProvider bodyDataProvider
   */
  public function testFormat(array $message, $expected, $expected_plain) {
    $message['module'] = 'swiftmailer';
    $message['key'] = 'FormatTest';
    $message['subject'] = 'FormatTest';

    $message['params']['content_type'] = SWIFTMAILER_FORMAT_HTML;
    $actual = $this->plugin->format($message);
    $expected = implode(PHP_EOL, $expected);
    $this->assertSame($expected, $this->extractBody($actual));

    $message['params']['content_type'] = SWIFTMAILER_FORMAT_PLAIN;
    $actual = $this->plugin->format($message);
    $expected_plain = implode(PHP_EOL, $expected_plain) . PHP_EOL;
    $this->assertSame($expected_plain, (string) $actual['body']);
  }

  /**
   * Tests messages with CSS.
   */
  public function testCss() {
    $message['module'] = 'swiftmailer';
    $message['key'] = 'FormatTest';
    $message['subject'] = 'FormatTest';
    $message['params']['content_type'] = SWIFTMAILER_FORMAT_HTML;
    $message['body'] = [Markup::create('<p class="red">Red text</p>')];
    $expected = '<p class="red" style="color: red;">Red text</p>';
    $actual = $this->plugin->format($message);
    $this->assertSame($expected, $this->extractBody($actual));
  }

  /**
   * Data provider of body data.
   */
  public function bodyDataProvider() {
    return [
      'with html' => [
        'message' => [
          'body' => [
            Markup::create('<p>Lorem ipsum &amp; dolor sit amet</p>'),
            Markup::create('<p>consetetur &lt; sadipscing elitr</p>'),
          ],
        ],
        'expected' => [
          "<p>Lorem ipsum &amp; dolor sit amet</p>",
          "<p>consetetur &lt; sadipscing elitr</p>",
        ],
        'expected_plain' => [
          "Lorem ipsum & dolor sit amet\n",
          "consetetur < sadipscing elitr",
        ],
      ],

      'no html' => [
        'message' => [
          'body' => [
            "Lorem ipsum & dolor sit amet\nconsetetur < sadipscing elitr",
            "URL is http://example.com",
          ],
        ],
        'expected' => [
          "<p>Lorem ipsum &amp; dolor sit amet<br>\nconsetetur &lt; sadipscing elitr</p>",
          '<p>URL is <a href="http://example.com">http://example.com</a></p>',
        ],
        'expected_plain' => [
          "Lorem ipsum & dolor sit amet\nconsetetur < sadipscing elitr",
          "URL is http://example.com",
        ],
      ],

      'mixed' => [
        'message' => [
          'body' => [
            'Hello & World',
            // Next, the content of the message contains strings that look like
            // markup.  For example it could be a website lecturer explaining
            // to students about the <strong> tag.
            'Hello & <strong>World</strong>',
            new FormattableMarkup('<p>Hello &amp; World #@number</p>', ['@number' => 2]),
            Markup::create('<p>Hello &amp; <strong>World</strong></p>'),
          ],
        ],
        'expected' => [
          "<p>Hello &amp; World</p>",
          "<p>Hello &amp; &lt;strong&gt;World&lt;/strong&gt;</p>",
          "<p>Hello &amp; World #2</p>",
          "<p>Hello &amp; <strong>World</strong></p>",
        ],
        'expected_plain' => [
          "Hello & World",
          "Hello & <strong>World</strong>\n",
          "Hello & World #2\n",
          "Hello & WORLD",
        ],
      ],
    ];
  }

  /**
   * Returns the HTML body from a message (contents of <body> tag).
   */
  protected function extractBody($message) {
    preg_match('|<html><body>(.*)</body></html>|s', $message['body'], $matches);
    return trim($matches[1]);
  }

}

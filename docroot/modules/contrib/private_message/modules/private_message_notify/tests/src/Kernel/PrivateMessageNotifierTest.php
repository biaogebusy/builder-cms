<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message_notify\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;
use Drupal\filter\Entity\FilterFormat;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\MessageTemplateInterface;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;

/**
 * @coversDefaultClass \Drupal\private_message_notify\Service\PrivateMessageNotifier
 * @group private_message
 */
class PrivateMessageNotifierTest extends KernelTestBase {

  use AssertMailTrait;
  use PrivateMessageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'mailsystem',
    'message',
    'message_notify',
    'private_message',
    'private_message_notify',
    'private_message_notify_test',
    'symfony_mailer_lite',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('name', 'Aerosmith')->save();

    // Theme us needed for mail body rendering.
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')
      ->set('admin', 'stark')
      ->set('default', 'stark')
      ->save();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->createTestingUsers();

    $this->installEntitySchema('message');
    $this->installEntitySchema('private_message_thread');
    $this->installEntitySchema('private_message');
    $this->installSchema('private_message', ['pm_thread_history']);

    $this->installConfig([
      'filter',
      'message',
      'message_notify',
      'private_message_notify',
    ]);

    // Enable notifications.
    $this->config('private_message.settings')
      ->set('enable_notifications', TRUE)
      ->set('notify_by_default', TRUE)
      ->save();

    // Use rich HTML in email body.
    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<p> <strong>',
          ],
        ],
      ],
    ])->save();
    $messageTemplate = MessageTemplate::load('private_message_notification');
    assert($messageTemplate instanceof MessageTemplateInterface);
    $text = $messageTemplate->get('text');
    $text[1]['format'] = 'basic_html';
    $messageTemplate->set('text', $text)->save();

    $this->config('mailsystem.settings')
      ->set('theme', 'stark')
      ->set('defaults', [
        'sender' => 'test_mail_collector',
        'formatter' => 'symfony_mailer_lite',
      ])->save();
  }

  /**
   * @covers ::notify
   * @covers \private_message_tokens
   */
  public function testEmailMarkup(): void {
    $thread = PrivateMessageThread::create([
      'members' => [$this->users['a'], $this->users['b']],
      'private_messages' => [],
    ]);

    $privateMessage = PrivateMessage::create([
      'owner' => $this->users['a'],
      'message' => [
        'value' => "<p><strong>Janie</strong>'s Got a Gun</p><script type=\"Danger!\"></script>",
        'format' => 'basic_html',
      ],
    ]);

    // Create a new.
    $this->container->get('private_message.thread_manager')
      ->saveThread($privateMessage, $thread->getMembers(), $thread);

    foreach ($this->getMails() as $mail) {
      if ($mail['module'] !== 'message_notify' || $mail['key'] !== 'private_message_notification') {
        continue;
      }

      $this->assertSame('Private message at Aerosmith', $mail['subject']);

      // Cast from MarkupInterface to string.
      $body = (string) $mail['body'];

      $this->assertStringContainsString("<p><strong>Janie</strong>'s Got a Gun</p>",
        $body);
      // .No encoded HTML.
      $this->assertStringNotContainsString('&lt;', $body);
      $this->assertStringNotContainsString('&gt;', $body);
      // Dangerous tags are stripped out.
      $this->assertStringNotContainsString('Danger!', $body);
      $this->assertStringNotContainsString('<script', $body);
      $this->assertStringNotContainsString('</script>', $body);
    }
  }

}

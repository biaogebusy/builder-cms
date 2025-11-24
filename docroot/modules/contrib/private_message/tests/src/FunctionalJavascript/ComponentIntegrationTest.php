<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\private_message\Traits\InboxBlockTestTrait;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;
use Drupal\Tests\private_message\Traits\ThreadMessageFormatterTestTrait;

/**
 * Tests how various components work together.
 *
 * @group private_message
 */
class ComponentIntegrationTest extends WebDriverTestBase {

  use PrivateMessageTestTrait;
  use ThreadMessageFormatterTestTrait;
  use InboxBlockTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'private_message'];

  /**
   * Threads.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageThreadInterface[]
   */
  protected array $threads;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->attachFullNameField();
    $this->createTestingUsers(3);

    $this->threads = [
      $this->createThreadWithMessages([
        $this->users['a'],
        $this->users['b'],
      ], $this->users['b']),
      $this->createThreadWithMessages([
        $this->users['a'],
        $this->users['c'],
      ], $this->users['c'], 2),
    ];
  }

  /**
   * Tests the impact of the thread message formatter on the Inbox Block.
   */
  public function testImpactOfThreadMessageFormatterOnInboxBlock(): void {
    $settings = [
      'ajax_refresh_rate' => 1,
    ];
    $this->drupalPlaceBlock('private_message_inbox_block', $settings);

    $firstThread = $this->threads[0];
    $this->drupalLogin($this->users['a']);
    $this->drupalGet('/private-messages/' . $firstThread->id());

    $messageFromUser = 'It is a long established fact.';
    $this->getSession()->getPage()->fillField('Message', $messageFromUser);
    $this->getSession()->getPage()->pressButton('Send');

    $this->assertSession()->assertWaitOnAjaxRequest();

    $threadElement = $this->assertSession()
      ->elementExists('css', '.private-message-thread-inbox[data-thread-id="' . $firstThread->id() . '"]');
    $this->assertStringContainsString($messageFromUser, $threadElement->getText(), 'Thread should be updated with a new message');
  }

  /**
   * Tests the impact of the Inbox Block on the thread message formatter.
   */
  public function testImpactOfInboxBlockOnThreadMessageFormatter(): void {
    $this->drupalPlaceBlock('private_message_inbox_block');

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('/private-messages');
    $this->assertEquals(2, $this->countMessages());

    $this->clickThread($this->threads[0]);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(1, $this->countMessages());
  }

}

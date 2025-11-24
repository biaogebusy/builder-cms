<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\private_message\Traits\InboxBlockTestTrait;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;

/**
 * JS tests for Private Message Inbox block functionalities.
 *
 * @group private_message
 */
class InboxBlockTest extends WebDriverTestBase {

  use PrivateMessageTestTrait;
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
    $this->createTestingUsers(4);

    $this->threads[] = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b']);

    $this->threads[] = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['c'],
    ], $this->users['c']);
  }

  /**
   * Tests inbox click functionality.
   */
  public function testInboxClick(): void {
    $this->drupalPlaceBlock('private_message_inbox_block');

    $this->drupalLogin($this->users['a']);

    // Latest thead must be active.
    $lastThread = $this->threads[count($this->threads) - 1];
    $element = $this->assertSession()
      ->elementExists('css', '.private-message-thread-inbox[data-thread-id="' . $lastThread->id() . '"]');
    $this->assertStringContainsString(
      'active-thread',
      $element->getAttribute('class'),
    );

    foreach ($this->threads as $thread) {
      $this->drupalGet('<front>');
      $this->clickThread($thread);
      $this->assertEquals(
        $this->getAbsoluteUrl('/private-messages/' . $thread->id()),
        $this->getUrl(),
      );
    }
  }

  /**
   * Tests load previous functionality.
   */
  public function testLoadPrevious(): void {
    // 2 more threads, together we have 4.
    $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['d'],
    ], $this->users['d']);

    $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
      $this->users['c'],
    ], $this->users['c']);

    $settings = [
      'thread_count' => 1,
      'ajax_load_count' => 2,
      'ajax_refresh_rate' => 100,
    ];
    $this->drupalPlaceBlock('private_message_inbox_block', $settings);

    $this->drupalLogin($this->users['a']);
    $this->assertEquals(1, $this->countThreads());

    $this->getSession()
      ->getPage()
      ->clickLink('Load Previous');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(3, $this->countThreads());

    $this->getSession()
      ->getPage()
      ->clickLink('Load Previous');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(4, $this->countThreads());
    $this->assertSession()
      ->pageTextNotContains('Load Previous');
  }

  /**
   * Tests display of a new thread without reloading page.
   */
  public function testNewThread(): void {
    $settings = [
      'ajax_refresh_rate' => 1,
    ];

    $this->drupalPlaceBlock('private_message_inbox_block', $settings);
    $this->drupalLogin($this->users['a']);

    $this->threads[] = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['d'],
    ], $this->users['d']);

    $this->threads[] = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
      $this->users['c'],
    ], $this->users['c']);

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertEquals(4, $this->countThreads());

    $threadIds = [];
    foreach ($this->getThreads() as $threadElement) {
      $threadIds[] = $threadElement->getAttribute('data-thread-id');
    }

    $expectedOrder = [];
    foreach ($this->threads as $thread) {
      $expectedOrder[] = $thread->id();
    }
    $expectedOrder = array_reverse($expectedOrder);

    $this->assertEquals($expectedOrder, $threadIds, 'Threads are not in the expected order.');
  }

}

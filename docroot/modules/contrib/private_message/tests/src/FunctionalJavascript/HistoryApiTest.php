<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\private_message\Traits\InboxBlockTestTrait;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;

/**
 * JS tests for History API.
 *
 * @group private_message
 */
class HistoryApiTest extends WebDriverTestBase {

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
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->createTestingUsers(3);
  }

  /**
   * Tests state history.
   */
  public function testStateHistory(): void {
    $this->drupalPlaceBlock('private_message_inbox_block');

    $threads[] = $this->createThreadWithMessages(
      [$this->users['a'], $this->users['b']],
      $this->users['b']
    );
    $threads[] = $this->createThreadWithMessages(
      [$this->users['a'], $this->users['c']],
      $this->users['c']
    );
    $threads[] = $this->createThreadWithMessages(
      [$this->users['a'], $this->users['b'], $this->users['c']],
      $this->users['c']
    );

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('/private-messages');

    foreach ($threads as $thread) {
      $this->clickThread($thread);
      $this->assertSession()->assertWaitOnAjaxRequest();

      $historyState = $this->getSession()
        ->evaluateScript('return window.history.state;');
      $this->assertEquals(['threadId' => $thread->id()], $historyState, 'The history state was replaced incorrectly.');

      $currentUrl = $this->getSession()->getCurrentUrl();
      $this->assertStringContainsString('/private-messages/' . $thread->id(), $currentUrl, 'The pushed URL is correct.');
    }
  }

}

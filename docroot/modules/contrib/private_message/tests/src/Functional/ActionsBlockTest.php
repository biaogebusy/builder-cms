<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;

/**
 * Tests for Private Message Actions block.
 *
 * @group private_message
 */
class ActionsBlockTest extends BrowserTestBase {

  use PrivateMessageTestTrait;

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
    $this->createTestingUsers(1);

    $this->config('private_message.settings')
      ->set('create_message_label', 'create_message_label_text')
      ->set('ban_page_label', 'ban_page_label_text')
      ->save();
    $this->drupalPlaceBlock('private_message_actions_block');
  }

  /**
   * Tests the block content.
   */
  public function testBlock(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->linkNotExists('create_message_label_text');
    $this->assertSession()->linkNotExists('ban_page_label_text');

    $this->drupalLogin($this->users['a']);
    $this->getSession()
      ->getPage()
      ->clickLink('create_message_label_text');
    $this->assertEquals(
      $this->getAbsoluteUrl('/private-message/create'),
      $this->getUrl(),
    );

    $this->getSession()
      ->getPage()
      ->clickLink('ban_page_label_text');
    $this->assertEquals(
      $this->getAbsoluteUrl('/private-message/ban'),
      $this->getUrl(),
    );
  }

}

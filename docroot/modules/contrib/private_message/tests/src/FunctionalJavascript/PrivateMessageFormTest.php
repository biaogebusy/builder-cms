<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;
use Drupal\Tests\private_message\Traits\ThreadMessageFormatterTestTrait;

/**
 * JS tests for Private Message Form.
 *
 * @group private_message
 */
class PrivateMessageFormTest extends WebDriverTestBase {

  use PrivateMessageTestTrait;
  use ThreadMessageFormatterTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['private_message'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->createTestingUsers();
  }

  /**
   * Tests key up.
   *
   * @dataProvider signConfigurationsProvider
   */
  public function testKeyUp(string $keuUp, string $char): void {
    $this->config('private_message.settings')
      ->set('keys_send', $keuUp . ',y')
      ->save();

    $thread = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b']);

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('/private-messages/' . $thread->id());

    $this->assertEquals(1, $this->countMessages());

    $textarea = $this->assertSession()
      ->elementExists('css', '.private-message-add-form textarea');
    $textarea->setValue('Second message');
    $textarea->focus();
    $this->getSession()->getDriver()->keyUp($textarea->getXpath(), $keuUp);

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(2, $this->countMessages());
    $this->assertSession()
      ->pageTextContains('Second message');
    $this->assertSession()
      ->pageTextNotContains('Second message' . $char);
  }

  /**
   * Data for the testKeyUp().
   */
  public static function signConfigurationsProvider(): array {
    return [
      'x sign' => ['88', 'x'],
      'Enter' => ['Enter', "\n"],
    ];
  }

}

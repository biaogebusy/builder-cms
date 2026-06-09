<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_api_explorer\FunctionalJavascript\Plugin\AiApiExplorer;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests the Chat Explorer.
 *
 * @group ai_api_explorer
 * @group 3577469
 */
class ChatExplorerTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'file',
    'ai_api_explorer',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_api_explorer';

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setDefaultProvider('chat', 'echoai', 'gpt-test');
  }

  /**
   * Tests to create a chat message and check the response.
   */
  public function testCreateChatMessageAndResponse(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access ai prompt',
    ]);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/ai/explorers/chat_generator');
    // Take a screenshot before interaction.
    $this->takeScreenshot('1_before_message');

    // Get the page.
    $page = $this->getSession()->getPage();

    // Fill in the chat message.
    $page->fillField('message_1', 'Hello There');

    // Take a screenshot after filling the form.
    $this->takeScreenshot('2_filled_form');

    // Press the Ask The AI button.
    $this->click('#edit-submit');

    // Take a screenshot after clicking the  button.
    $this->takeScreenshot('3_after_click_button');

    // Wait for ajax to complete.
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Take a screenshot after the ajax call.
    $this->takeScreenshot('4_after_ajax_call');

    // Wait for the response text to appear in the DOM before asserting.
    $this->assertSession()->waitForText('Hello! How can I help you today?');

    // Find the response.
    $this->assertSession()->pageTextContains('Hello! How can I help you today? 😊');
  }

}

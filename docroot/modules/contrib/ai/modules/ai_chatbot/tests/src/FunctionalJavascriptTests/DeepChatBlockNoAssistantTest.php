<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\FunctionalJavascriptTests;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests DeepChat block does not crash when no assistant is configured.
 *
 * @group ai_chatbot
 * @group 3577813
 */
class DeepChatBlockNoAssistantTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'ai_assistant_api',
    'ai_chatbot',
    'block',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_chatbot';

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * Tests that the front page loads with a 200 when the block has no assistant.
   */
  public function testFrontPageLoadsWithBlockWithoutAssistant(): void {
    $admin = $this->drupalCreateUser([
      'administer blocks',
      'access content',
    ]);
    $this->drupalLogin($admin);

    // Place the DeepChat block in the content region with no assistant.
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Test Chatbot',
      'ai_assistant' => '',
    ]);

    // Visit the front page as an anonymous user.
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->takeScreenshot('1_front_page_no_assistant');

    // The page should load successfully (no error/crash).
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');

    // The block should not be visible since no assistant is configured.
    $this->assertSession()->pageTextNotContains('Test Chatbot');
    $this->takeScreenshot('2_block_not_visible');
  }

}

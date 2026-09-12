<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\ai\Entity\AiPrompt;
use Drupal\ai\Entity\AiPromptType;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that raw Drupal tokens survive the MDXEditor source/rich-text toggle.
 *
 * @group ai
 * @group 3586613
 */
#[RunTestsInSeparateProcesses]
class MdxEditorTokenEscapingTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // A prompt type with a bracket-shaped token loads the typeahead plugin,
    // which is what pulls the directive grammar into MDXEditor's parser.
    AiPromptType::create([
      'id' => 'token_escaping_test',
      'label' => 'Token Escaping Test',
      'tokens' => [
        [
          'name' => 'node:title',
          'help_text' => 'The node title',
          'required' => FALSE,
        ],
      ],
    ])->save();

    AiPrompt::create([
      'id' => 'token_escaping_test__prompt',
      'label' => 'Token Escaping Test Prompt',
      'type' => 'token_escaping_test',
      'prompt' => 'Initial prompt text',
    ])->save();

    $this->drupalLogin($this->drupalCreateUser(['manage ai prompts']));
  }

  /**
   * Switches the field to source mode, types markdown, switches back.
   *
   * @param string $markdown
   *   The raw markdown to type into the CodeMirror source view.
   */
  protected function typeInSourceModeAndSwitchToRichText(string $markdown): void {
    $this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [contenteditable="true"]');

    $this->assertSession()->waitForElementVisible('css', 'button[aria-label="Source mode"]')->click();
    $editable = $this->assertSession()->waitForElementVisible('css', '.cm-content');
    $this->assertNotEmpty($editable);
    $editable->click();
    $this->getSession()->executeScript(<<<JS
      const cm = document.querySelector('.cm-content');
      cm.focus();
      document.execCommand('selectAll', false, null);
      document.execCommand('insertText', false, {$this->jsonEncodeForScript($markdown)});
    JS);

    $this->assertSession()->waitForElementVisible('css', 'button[aria-label="Rich text"]')->click();
  }

  /**
   * JSON-encodes a string for safe interpolation into an executeScript() body.
   */
  protected function jsonEncodeForScript(string $value): string {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  }

  /**
   * Tests that a raw Drupal token typed in source mode round-trips intact.
   *
   * It must not break the rich-text view and must round-trip back into the
   * saved value unchanged.
   */
  public function testRawTokenRoundTrips(): void {
    $this->drupalGet('admin/config/ai/prompts/token_escaping_test__prompt');
    $this->takeScreenshot('1_1_prompt_edit_form_loaded');

    $this->typeInSourceModeAndSwitchToRichText('[node:title]');
    $this->takeScreenshot('1_2_after_switch_to_rich_text');

    // The parse error must not surface, and the rich-text editor must remain
    // the visible view (DiffSourceWrapper hides it while an error is set).
    $this->assertSession()->pageTextNotContains('Parsing of the following markdown structure failed');
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [contenteditable="true"]'));

    $value = $this->getSession()->evaluateScript(
      "document.querySelector('textarea[name=\"prompt\"]').value"
    );
    $this->assertSame('[node:title]', trim($value), 'The hidden textarea holds the exact raw token, with no backslash escaping.');
  }

  /**
   * A chained/nested token must also round-trip unchanged.
   */
  public function testNestedTokenRoundTrips(): void {
    $this->drupalGet('admin/config/ai/prompts/token_escaping_test__prompt');

    $this->typeInSourceModeAndSwitchToRichText('[node:field-x:entity:title]');
    $this->takeScreenshot('2_1_after_nested_token_round_trip');

    $this->assertSession()->pageTextNotContains('Parsing of the following markdown structure failed');
    $value = $this->getSession()->evaluateScript(
      "document.querySelector('textarea[name=\"prompt\"]').value"
    );
    $this->assertSame('[node:field-x:entity:title]', trim($value));
  }

  /**
   * A real markdown link must be left completely unaffected by the fix.
   */
  public function testLegitimateMarkdownLinkIsUnaffected(): void {
    $this->drupalGet('admin/config/ai/prompts/token_escaping_test__prompt');

    $this->typeInSourceModeAndSwitchToRichText('[Read more](https://example.com)');
    $this->takeScreenshot('3_1_after_link_round_trip');

    $this->assertSession()->pageTextNotContains('Parsing of the following markdown structure failed');
    $value = $this->getSession()->evaluateScript(
      "document.querySelector('textarea[name=\"prompt\"]').value"
    );
    $this->assertSame('[Read more](https://example.com)', trim($value));
  }

}

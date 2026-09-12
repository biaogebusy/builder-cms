<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the default tools editor re-attaches after an AJAX rebuild.
 *
 * @group ai
 * @group 3586635
 */
#[RunTestsInSeparateProcesses]
class DefaultToolsEditorAjaxAttachmentTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
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
    $this->drupalLogin($this->drupalCreateUser(['administer ai']));
  }

  /**
   * {@inheritdoc}
   *
   * The editor's Interactive mode calls crypto.randomUUID(), which Chrome
   * only exposes in a secure context (https, or http on localhost/127.0.0.1).
   * The functional-test server runs on plain http://web, so without this
   * flag the editor cannot ever enter Interactive mode - it always falls
   * back to YAML mode with a "could not be parsed" error, regardless of
   * what the textarea actually contains.
   */
  protected function getMinkDriverArgs(): bool|string {
    $json = parent::getMinkDriverArgs();
    if ($json) {
      $args = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      if (isset($args[1]['goog:chromeOptions']['args'])) {
        $origin = getenv('SIMPLETEST_BASE_URL') ?: 'http://web';
        $args[1]['goog:chromeOptions']['args'][] = '--unsafely-treat-insecure-origin-as-secure=' . $origin;
      }
      $json = json_encode($args, JSON_THROW_ON_ERROR);
    }
    return $json;
  }

  /**
   * The editor must attach on load and re-attach after an AJAX rebuild.
   *
   * The [data-default-tools-editor] textarea is processed by
   * Drupal.behaviors.aiDefaultToolsEditor, which calls
   * once('ai-default-tools-editor', ...). A processed element carries a
   * data-once="ai-default-tools-editor" attribute, so that attribute is
   * the observable signal that the behavior has (re-)attached.
   */
  public function testEditorReattachesAfterAjaxRebuild(): void {
    $assert = $this->assertSession();

    $this->drupalGet('admin/config/ai/test-default-tools-editor-ajax');

    $initial = $assert->waitForElement('css', '[data-default-tools-editor][data-once~="ai-default-tools-editor"]');
    $this->assertNotEmpty($initial, 'The default tools editor attaches to the textarea on initial page load.');
    $initialMarker = $assert->elementExists('css', '[data-rebuild-marker="initial"]');
    $this->assertSame('Rebuild marker: initial load', trim($initialMarker->getText()));
    // The data-once marker is set synchronously on attach, a moment before
    // React actually mounts the editor UI next to the textarea. Wait for the
    // mount point so the screenshot shows the rendered editor, not a raw
    // textarea that merely carries the marker.
    $assert->waitForElementVisible('css', '[data-ai-agents-default-tools-editor-root]');
    $this->assertInteractiveModeActiveAndScreenshot('1_initial_interactive_view');

    $this->getSession()->getPage()->pressButton('Update via AJAX');
    $assert->assertWaitOnAjaxRequest();

    // The #markup marker is not subject to form-input value processing (as
    // the textarea's own value would be), so its change from "initial" to
    // "rebuilt" reliably proves the wrapper - and the textarea inside it -
    // were replaced by the AJAX response, not left untouched.
    $rebuiltMarker = $assert->waitForElement('css', '[data-rebuild-marker="rebuilt"]');
    $this->assertNotEmpty($rebuiltMarker, 'The rebuild marker switches to "rebuilt" once the AJAX response lands.');
    $this->assertSame('Rebuild marker: rebuilt via AJAX', trim($rebuiltMarker->getText()));
    $rebuilt = $assert->waitForElement('css', '[data-default-tools-editor][data-once~="ai-default-tools-editor"]');
    $this->assertNotEmpty($rebuilt, 'The default tools editor re-attaches to the textarea returned by the AJAX callback.');
    $assert->waitForElementVisible('css', '[data-ai-agents-default-tools-editor-root]');
    $this->assertInteractiveModeActiveAndScreenshot('2_rebuilt_interactive_view');
  }

  /**
   * Malformed YAML must fall back to YAML mode, never Interactive mode.
   *
   * ToolsEditor.jsx's mount-time parse of the textarea's value is wrapped in
   * a try/catch: on failure it forces mode: 'yaml' and shows an error,
   * rather than entering Interactive mode with no (or garbage) data. The
   * ai_test form's "malformed" query parameter swaps in a YAML value (an
   * unterminated flow sequence) that js-yaml cannot parse.
   */
  public function testMalformedYamlFallsBackToYamlMode(): void {
    $assert = $this->assertSession();

    $this->drupalGet('admin/config/ai/test-default-tools-editor-ajax', ['query' => ['malformed' => 1]]);

    $textarea = $assert->waitForElement('css', '[data-default-tools-editor][data-once~="ai-default-tools-editor"]');
    $this->assertNotEmpty($textarea, 'The default tools editor still attaches even when the textarea holds malformed YAML.');

    $activeTab = $assert->waitForElementVisible('css', '.tools-editor__toggle--active');
    $this->assertNotEmpty($activeTab, 'A tab is active in the tools editor.');
    $this->assertSame('YAML', trim($activeTab->getText()), 'Malformed YAML forces the fallback YAML tab, not Interactive mode.');

    $errorMessage = $assert->waitForElementVisible('css', '.tools-editor__error');
    $this->assertNotEmpty($errorMessage, 'An error message explains why Interactive mode could not be used.');
    $this->assertStringContainsString('could not be parsed', $errorMessage->getText());

    $assert->elementNotExists('css', '.tools-editor__add');
    $this->assertTrue($textarea->isVisible(), 'The raw textarea remains visible as the YAML fallback view.');
    $this->assertStringContainsString('unterminated', $textarea->getValue(), 'The unparsable YAML is still shown, unaltered, in the fallback textarea.');

    $this->takeScreenshot('3_malformed_yaml_fallback_view');
  }

  /**
   * Asserts the (newly mounted) editor opened directly in Interactive mode.
   *
   * ToolsEditor.jsx defaults to Interactive mode whenever the textarea's
   * value parses successfully, so no tab click is needed here - only a
   * failed parse would fall back to YAML mode. The "+ Add Tool" button only
   * renders in Interactive mode, so waiting for it proves the real
   * tool-editing UI - not just the YAML textarea - is what ends up in the
   * screenshot.
   *
   * @param string $screenshotName
   *   The screenshot filename to use.
   */
  protected function assertInteractiveModeActiveAndScreenshot(string $screenshotName): void {
    $activeTab = $this->assertSession()->waitForElementVisible('css', '.tools-editor__toggle--active');
    $this->assertNotEmpty($activeTab, 'A tab is active in the tools editor.');
    $this->assertSame('Interactive', trim($activeTab->getText()), 'The editor opens directly into Interactive mode by default.');

    $addToolButton = $this->assertSession()->waitForElementVisible('css', '.tools-editor__add');
    $this->assertNotEmpty($addToolButton, 'The editor is in Interactive mode and rendered its "Add Tool" button.');
    $this->takeScreenshot($screenshotName);
  }

}

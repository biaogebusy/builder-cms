<?php

namespace Drupal\Tests\ai_ckeditor\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;

/**
 * Tests the AI CKEditor admin toolbar configuration.
 *
 * @group ai_ckeditor
 * @group 3477173
 */
class AiCKEditorAdminTest extends BaseClassFunctionalJavascriptTests {

  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'ai_ckeditor',
    'ckeditor5',
    'editor',
    'filter',
    'node',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_ckeditor';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a text format.
    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<br> <p> <h2> <h3> <h4> <h5> <h6> <strong> <em>',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Tests the AI CKEditor toolbar configuration.
   */
  public function testAiCkeditorConfigurations(): void {
    $admin = $this->drupalCreateUser([
      'administer filters',
      'use text format test_format',
    ]);
    $this->drupalLogin($admin);

    // Visit the text format configuration page.
    $this->drupalGet('admin/config/content/formats/manage/test_format');
    $this->assertSession()->pageTextNotContains('Access denied');
    $this->takeScreenshot('1_format_page_before_editor');

    // Select CKEditor 5 as the text editor.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Text editor', 'ckeditor5');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('2_ckeditor5_selected');

    // Ensure the AI CKEditor button is available in the disabled toolbar area.
    $this->assertSession()->elementExists(
      'css',
      '.ckeditor5-toolbar-disabled .ckeditor5-toolbar-item-aickeditor'
    );
    $this->takeScreenshot('3_ai_button_available');

    // Ensure plugin config sections are not yet present before enabling.
    foreach ($this->getAiCkeditorPlugins() as $plugin_id => $label) {
      $this->assertSession()->elementNotExists('css', sprintf(
        'details[data-drupal-selector="edit-editor-settings-plugins-ai-ckeditor-ai-plugins-%s"]',
        str_replace('_', '-', $plugin_id)
      ));
    }

    // Enable the AI CKEditor button by moving it to the active toolbar.
    $this->triggerKeyUp('.ckeditor5-toolbar-item-aickeditor', 'ArrowDown');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('4_ai_button_in_active_toolbar');

    // Ensure the AI CKEditor button is now in the active toolbar.
    $this->assertSession()->elementExists(
      'css',
      '.ckeditor5-toolbar-active .ckeditor5-toolbar-item-aickeditor'
    );
    $this->assertSession()->elementNotExists(
      'css',
      '.ckeditor5-toolbar-disabled .ckeditor5-toolbar-item-aickeditor'
    );

    // Ensure each discovered plugin has a config section and enable it.
    foreach ($this->getAiCkeditorPlugins() as $plugin_id => $label) {
      $this->assertPluginConfigExists($plugin_id, $label);
      $this->enablePlugin($plugin_id);
      $this->assertSession()->assertWaitOnAjaxRequest();
    }
    $this->takeScreenshot('5_plugin_configs_present');

    // Save the text format configuration.
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->takeScreenshot('6_configuration_saved');

    // Ensure the configuration was saved without errors or warnings.
    $this->assertSession()->pageTextContains('The text format Test format has been updated');
    $this->assertSession()->elementNotExists('css', '.messages--error');
    $this->assertSession()->elementNotExists('css', '.messages--warning');
  }

  /**
   * Clicks the Enabled checkbox for a given AI CKEditor plugin.
   *
   * The checkbox data-drupal-selector follows the pattern:
   * edit-editor-settings-plugins-ai-ckeditor-ai-plugins-{id}-enabled.
   *
   * @param string $plugin_id
   *   The plugin machine name (e.g. 'ai_ckeditor_spellfix').
   */
  protected function enablePlugin(string $plugin_id): void {
    $selector = sprintf(
      'input[data-drupal-selector="edit-editor-settings-plugins-ai-ckeditor-ai-plugins-%s-enabled"]',
      str_replace('_', '-', $plugin_id)
    );
    $this->getSession()->executeScript("document.querySelector('$selector').click();");
  }

  /**
   * Returns all discovered AI CKEditor plugin IDs and their labels.
   *
   * Uses the plugin manager so the assertion stays in sync with any new
   * plugins added to the module without needing to update this test.
   *
   * @return array<string, string>
   *   Keyed by plugin ID, values are the human-readable label strings.
   */
  protected function getAiCkeditorPlugins(): array {
    $definitions = $this->container
      ->get('plugin.manager.ai_ckeditor')
      ->getDefinitions();

    $plugins = [];
    foreach ($definitions as $id => $definition) {
      $plugins[$id] = (string) $definition['label'];
    }
    return $plugins;
  }

  /**
   * Asserts that the config section for a given AI CKEditor plugin is present.
   *
   * Each plugin renders a <details> element whose data-drupal-selector follows
   * the pattern: edit-editor-settings-plugins-ai-ckeditor-ai-plugins-{id}.
   * The summary inside contains the human-readable plugin label.
   *
   * @param string $plugin_id
   *   The plugin machine name (e.g. 'ai_ckeditor_spellfix').
   * @param string $label
   *   The expected human-readable label (e.g. 'Fix spelling').
   */
  protected function assertPluginConfigExists(string $plugin_id, string $label): void {
    $selector = sprintf(
      'details[data-drupal-selector="edit-editor-settings-plugins-ai-ckeditor-ai-plugins-%s"]',
      str_replace('_', '-', $plugin_id)
    );
    $this->assertSession()->elementExists('css', $selector);
    $this->assertSession()->elementTextContains('css', "$selector summary", $label);
  }

  /**
   * Triggers keydown and keyup events on a toolbar item element.
   *
   * Used to move buttons between the available and active toolbar areas in the
   * CKEditor 5 admin UI, which relies on keyboard events via Sortable.js.
   *
   * @param string $selector
   *   The CSS selector for the element.
   * @param string $key
   *   The key value (e.g. 'ArrowDown' to move to active, 'ArrowUp' to remove).
   *
   * @see \Drupal\Tests\ckeditor5\FunctionalJavascript\CKEditor5TestBase::triggerKeyUp()
   */
  protected function triggerKeyUp(string $selector, string $key): void {
    $script = <<<JS
(function (selector, key) {
  const btn = document.querySelector(selector);
  btn.dispatchEvent(new KeyboardEvent('keydown', { key }));
  btn.dispatchEvent(new KeyboardEvent('keyup', { key }));
})('{$selector}', '{$key}')
JS;
    $this->getSession()->executeScript($script);
  }

}

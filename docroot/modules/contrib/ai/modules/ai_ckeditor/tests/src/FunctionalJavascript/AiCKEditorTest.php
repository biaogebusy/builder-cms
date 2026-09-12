<?php

namespace Drupal\Tests\ai_ckeditor\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the AI CKEditor plugin end-to-end functionality.
 *
 * @group ai_ckeditor
 * @group 3477173
 */
class AiCKEditorTest extends BaseClassFunctionalJavascriptTests {

  use CKEditor5TestTrait;
  use ContentTypeCreationTrait;

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
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_ckeditor';

  /**
   * {@inheritDoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   *
   * The AI dialog's autoresize option combined with the streamed response
   * being written into the nested CKEditor5 instance triggers an unrelated,
   * unresolved core race condition in dialog.position.js resetSize()
   * (event.data is undefined for a debounced drupalViewportOffsetChange
   * event). See https://www.drupal.org/project/drupal/issues/3356667.
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create article content type with body field.
    $this->createContentType(['type' => 'article', 'name' => 'Article']);

    // Create Full HTML format as the default (lowest weight).
    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => -10,
    ])->save();

    Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'heading',
            'bold',
            'italic',
            'sourceEditing',
            'aickeditor',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => [],
          ],
          'ai_ckeditor_ai' => [
            'dialog' => [
              'autoresize' => 'min-width: 600px',
              'height' => '750',
              'width' => '900',
              'dialog_class' => 'ai-ckeditor-modal',
            ],
            'plugins' => [
              'ai_ckeditor_completion' => [
                'enabled' => TRUE,
                'provider' => 'echoai__gpt-test',
              ],
            ],
          ],
        ],
      ],
    ])->save();

    // Configure EchoAI as default provider.
    \Drupal::service('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers', [
        'chat' => [
          'provider_id' => 'echoai',
          'model_id' => 'gpt-test',
        ],
      ])
      ->save();
  }

  /**
   * Tests that clicking the AI button opens the dialog.
   */
  public function testAiDialogOpens(): void {
    $user = $this->drupalCreateUser([
      'create article content',
      'use text format full_html',
      'use ai ckeditor',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('node/add/article');
    $this->waitForEditor();
    $this->takeScreenshot('1_ckeditor_loaded');

    // Click the AI Assistant dropdown button in the CKEditor5 toolbar.
    $this->pressEditorButton('AI Assistant');
    $this->takeScreenshot('2_dropdown_opened');

    // Click "Generate with AI" in the dropdown list.
    $this->pressEditorButton('Generate with AI');

    // Wait for the modal dialog to appear.
    $modal = $this->assertSession()->waitForElementVisible(
      'css',
      '.ui-dialog.ai-ckeditor-modal'
    );
    $this->assertNotEmpty($modal, 'AI dialog opened.');
    $this->takeScreenshot('3_dialog_opened');

    // Verify dialog contains expected form elements.
    $this->assertSession()->elementExists(
      'css',
      '.ui-dialog textarea[name="plugin_config[text_to_submit]"]'
    );
    $this->assertSession()->elementExists(
      'css',
      '.ui-dialog input[value="Generate"]'
    );
  }

  /**
   * Tests the AI dialog form interaction.
   */
  public function testAiDialogFormInteraction(): void {
    $user = $this->drupalCreateUser([
      'create article content',
      'use text format full_html',
      'use ai ckeditor',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('node/add/article');
    $this->waitForEditor();

    // Open AI dialog via toolbar.
    $this->pressEditorButton('AI Assistant');
    $this->pressEditorButton('Generate with AI');

    // Wait for dialog form.
    $modal = $this->assertSession()->waitForElementVisible(
      'css',
      '.ui-dialog.ai-ckeditor-modal'
    );
    $this->assertNotEmpty($modal, 'AI dialog opened.');
    $this->takeScreenshot('4_generation_dialog');

    // Fill in the prompt.
    $prompt_field = $this->assertSession()->waitForElementVisible(
      'css',
      '.ui-dialog textarea[name="plugin_config[text_to_submit]"]'
    );
    $this->assertNotEmpty($prompt_field, 'Prompt textarea found.');
    $prompt_field->setValue('Hello world test');
    $this->takeScreenshot('5_prompt_filled');

    // Click Generate button and verify AJAX triggers.
    $generate_btn = $this->assertSession()->elementExists(
      'css',
      '.ui-dialog input[value="Generate"]'
    );
    $generate_btn->click();
    $this->takeScreenshot('6_generate_clicked');

    // Wait for the AJAX response area to update.
    $this->getSession()->wait(10000, "
      document.querySelector('#ai-ckeditor-response') !== null &&
      document.querySelector('#ai-ckeditor-response').innerHTML.trim().length > 0
    ");
    $this->takeScreenshot('7_response_received');

    // Verify the response area exists.
    $this->assertSession()->elementExists('css', '#ai-ckeditor-response');
  }

}

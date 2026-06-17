<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\layout_builder\FunctionalJavascript\InlineBlockTestBase;
use Drupal\Tests\layout_builder_st\Functional\TranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that inline blocks works with content translation.
 *
 * @group layout_builder_st
 */
#[Group('layout_builder_st')]
#[RunTestsInSeparateProcesses]
class InlineBlockTranslationTest extends InlineBlockTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use JavascriptTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'field_ui',
    'layout_builder_st',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Adds a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the node type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'bundle_with_section_field', TRUE);
  }

  /**
   * Tests that inline blocks works with content translation.
   */
  public function testInlineBlockContentTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
      'create and edit custom blocks',
    ]));

    // Allow layout overrides.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $this->submitForm(['layout[allow_custom]' => TRUE], 'Save');

    // Add a new inline block to the original node.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block en label', 'Block en body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en label');
    $assert_session->pageTextContains('Block en body');
    $block_id = $this->getLatestBlockEntityId();

    // Check that when after editing an existing inline block, and then
    // re-editing, the serialized block config data is displayed in the block
    // edit form the second time, and not the block active revision data.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('Block en body', 'Block en body update');
    // Forcing a reload because somehow the Configure contextual link is not
    // accessible by the browser in the test.
    $this->drupalGet('node/1/layout');
    // Make sure the block body textarea contains 'Block en body update', then
    // restore to 'Block en body'. Discard changes to continue with the block
    // unchanged for other tests.
    $this->configureInlineBlock('Block en body update', 'Block en body');
    $page = $this->getSession()->getPage();
    $page->pressButton('Discard changes');
    $page->pressButton('Confirm');

    // Create a translation.
    $add_translation_url = Url::fromRoute("entity.node.content_translation_add", [
      'node' => 1,
      'source' => 'en',
      'target' => 'it',
    ]);
    $this->drupalGet($add_translation_url);
    $this->submitForm([
      'title[0][value]' => 'The translated node title',
      'body[0][value]' => 'The translated node body',
    ], 'Save');

    // Update the translate node's inline block.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();

    $this->updateBlockTranslation(
      static::INLINE_BLOCK_LOCATOR,
      'Block en label',
      'Block it label',
      '',
      ['[name="settings[block_form][body][0][value]"]']
    );

    $this->assertSaveLayout();
    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created.');
    $this->blockStorage->resetCache([$block_id]);
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $this->blockStorage->load($block_id);
    $this->assertFalse($block->hasTranslation('it'), 'A block translation was not created when only the label was translatable.');

    // Enable translation for block_content type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('block_content', 'basic', TRUE);

    // Update the translate node's inline block.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();
    $this->updateTranslatedBlock('Block it label', 'Block en body', 'Block updated it label', 'Block it body');

    $this->assertSaveLayout();
    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created');
    $this->blockStorage->resetCache([$block_id]);
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $this->blockStorage->load($block_id);
    $this->assertTrue($block->hasTranslation('it'), 'A block translation was created when the layout was saved.');
    $block_translation = $block->getTranslation('it');
    $this->assertEquals('Block it body', $block_translation->get('body')->get(0)->getValue()['value'], 'The translated block body field was created correctly.');

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block it body');
    $assert_session->pageTextContains('Block updated it label');
    $assert_session->pageTextNotContains('Block en body');
    $assert_session->pageTextNotContains('Block en label');

    // Confirm that the default translation was not effected.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block it body');
    $assert_session->pageTextNotContains('Block updated it label');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextContains('Block en label');

    // Update the translation inline block again.
    $this->drupalGet('it/node/1/layout');
    $this->updateTranslatedBlock('Block updated it label', 'Block it body', 'Block newer updated it label', 'Block updated it body');
    $this->assertSaveLayout();

    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created.');
    $this->blockStorage->resetCache([$block_id]);
    $block = $this->blockStorage->load($block_id);
    $block_translation = $block->getTranslation('it');
    $this->assertEquals('Block updated it body', $block_translation->get('body')->get(0)->getValue()['value'], 'The translated block body field was created correctly.');

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block updated it body');
    $assert_session->pageTextContains('Block newer updated it label');
    $assert_session->pageTextNotContains('Block en body');
    $assert_session->pageTextNotContains('Block en label');

    // Confirm that the default translation was not effected.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block updated it body');
    $assert_session->pageTextNotContains('Block newer updated it label');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextContains('Block en label');

    // Update the default translation's version of the block.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('Block en body', 'Block updated en body');
    $this->assertSaveLayout();

    $assert_session->addressEquals('node/1');
    $assert_session->pageTextNotContains('Block updated it body');
    $assert_session->pageTextNotContains('Block newer updated it label');
    $assert_session->pageTextContains('Block updated en body');
    $assert_session->pageTextContains('Block en label');

    // Confirm that the translation was not effected.
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('Block updated it body');
    $assert_session->pageTextContains('Block newer updated it label');
    $assert_session->pageTextNotContains('Block updated en body');
    $assert_session->pageTextNotContains('Block en label');

    // Update the translation block after updating default translation block.
    $this->drupalGet('it/node/1/layout');
    $this->updateTranslatedBlock('Block newer updated it label', 'Block updated it body', 'Block even newer updated it label', 'Block newer updated it body');
    $this->assertSaveLayout();

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block newer updated it body');
    $assert_session->pageTextContains('Block even newer updated it label');
    $assert_session->pageTextNotContains('Block updated en body');
    $assert_session->pageTextNotContains('Block en label');

    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block newer updated it body');
    $assert_session->pageTextNotContains('Block even newer updated it label');
    $assert_session->pageTextContains('Block updated en body');
    $assert_session->pageTextContains('Block en label');
  }

  /**
   * Update a translation inline block.
   *
   * @param string $existing_label
   *   The inline block's existing label.
   * @param string $existing_body
   *   The inline block's existing body field value.
   * @param string $new_label
   *   The new label.
   * @param string $new_body
   *   The new body field value.
   */
  protected function updateTranslatedBlock($existing_label, $existing_body, $new_label, $new_body) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink(static::INLINE_BLOCK_LOCATOR, 'Translate block');
    $textarea = $assert_session->waitForElement('css', '[name="body[0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertEquals($existing_body, $textarea->getValue());
    $textarea->setValue($new_body);

    $label_input = $assert_session->elementExists('css', '#drupal-off-canvas [name="info[0][value]"]');
    $this->assertNotEmpty($label_input);
    $this->assertEquals($existing_label, $label_input->getValue());
    $label_input->setValue($new_label);
    $page->pressButton('Save');

    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextContains($new_label);
    $assert_session->pageTextContains($new_body);
    $assert_session->pageTextNotContains($existing_label);
    $assert_session->pageTextNotContains($existing_body);
  }

}

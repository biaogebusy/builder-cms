<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\layout_builder_st\Functional\TranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test moderated and translated layout overrides.
 *
 * @group layout_builder_st
 */
#[Group('layout_builder_st')]
#[RunTestsInSeparateProcesses]
class ModeratedTranslationTest extends WebDriverTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use JavascriptTranslationTestTrait;
  use ContextualLinkClickTrait;
  use ContentModerationTestTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'content_moderation',
    'field_ui',
    'layout_builder',
    'block',
    'node',
    'contextual',
    'layout_builder_test',
    'layout_builder_st',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $page = $this->getSession()->getPage();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'bundle_with_section_field', 'new_revision' => TRUE]);
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'bundle_with_section_field');
    $workflow->save();

    // Adds a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the node type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'bundle_with_section_field', TRUE);

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
      'edit any bundle_with_section_field content',
      'view bundle_with_section_field revisions',
      'revert bundle_with_section_field revisions',
      'view own unpublished content',
      'view latest version',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]));

    $node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);

    $this->drupalGet('node/1');

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

    // Allow layout overrides.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(
      ['layout[enabled]' => TRUE],
      'Save'
    );
    $this->submitForm(
      ['layout[allow_custom]' => TRUE],
      'Save'
    );

    // Publish both nodes.
    $this->drupalGet($node->toUrl());
    $page->fillField('new_state', 'published');
    $page->pressButton('Apply');

    // Modify the layout.
    $this->drupalGet('it/node/1');
    $page->fillField('new_state', 'published');
    $page->pressButton('Apply');
  }

  /**
   * Tests a layout overrides that are moderated and translated.
   */
  public function testModerationTranslatedOverrides() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $node = Node::load(1);

    // Create a draft layout override.
    $this->drupalGet($node->toUrl());
    $page->clickLink('Layout');
    $assert_session->checkboxChecked('revision');
    $assert_session->fieldDisabled('revision');

    $this->addBlock('Powered by Drupal', '.block-system-powered-by-block', TRUE, 'untranslated label');
    $page->fillField('moderation_state[0][state]', 'draft');
    $page->pressButton('Save layout');

    // Modify the layout.
    $this->drupalGet('it/node/1');
    // Layout link does not exist for translation because published default
    // translation has no override.
    $assert_session->elementNotExists('css', '[data-drupal-link-system-path="node/1/layout"]');

    // Publish the override.
    $this->drupalGet($node->toUrl());
    $page->clickLink('Layout');
    $page->fillField('moderation_state[0][state]', 'published');
    $page->pressButton('Save layout');

    $assert_session->addressEquals('node/1');
    $assert_session->pageTextContains('untranslated label');

    $this->drupalGet('it/node/1');
    // Layout link exists for the translation after publish default translation
    // has an override.
    $assert_session->elementExists('css', '[data-drupal-link-system-path="node/1/layout"]');
    $page->clickLink('Layout');

    $assert_session->checkboxChecked('revision');
    $assert_session->fieldDisabled('revision');
    $assert_session->pageTextContains('untranslated label');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'untranslated label', 'label in translation');
    $page->fillField('moderation_state[0][state]', 'draft');
    $page->pressButton('Save layout');

    // The translate draft label is not show in any publish revision yet.
    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');
    $page->clickLink('Layout');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');

    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');

    $page->clickLink('Latest version');
    $assert_session->pageTextContains('label in translation');

    // Add a new block to the default translation override.
    $this->drupalGet($node->toUrl());
    $page->clickLink('Layout');
    $this->addBlock('Language switcher', '#layout-builder .block-language.block', TRUE, 'untranslated new label');
    $page->fillField('moderation_state[0][state]', 'draft');
    $page->pressButton('Save layout');

    $this->drupalGet('it/node/1');
    $this->clickLink('Layout');
    $assert_session->pageTextContains('label in translation');
    $assert_session->pageTextNotContains('untranslated new label');

    // Publish draft default translation with new block.
    $this->drupalGet($node->toUrl());
    $page->clickLink('Layout');
    $page->fillField('moderation_state[0][state]', 'published');
    $page->pressButton('Save layout');
    $assert_session->addressEquals('node/1');
    $assert_session->pageTextContains('untranslated new label');

    $this->drupalGet('it/node/1');
    // New block in published default translation exists in published
    // translation.
    $assert_session->pageTextContains('untranslated new label');
    $page->clickLink('Latest version');
    // New block in published default translation does not exist in existing
    // draft.
    $assert_session->pageTextNotContains('untranslated new label');
    $this->clickLink('Layout');
    $assert_session->pageTextContains('label in translation');
    // New block in published default translation does not exist in existing
    // draft.
    $assert_session->pageTextNotContains('untranslated new label');
    $page->fillField('moderation_state[0][state]', 'published');
    $page->pressButton('Save layout');

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('label in translation');

    $assert_session->pageTextNotContains('untranslated label');
    $assert_session->pageTextContains('untranslated new label');

    // The default translation still uses the untranslated label.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');
    $page->clickLink('Layout');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');
  }

}

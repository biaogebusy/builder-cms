<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\layout_builder_st\Functional\TranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that block settings can be translated.
 *
 * @group layout_builder_st
 */
#[Group('layout_builder_st')]
#[RunTestsInSeparateProcesses]
class TranslationTest extends WebDriverTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use JavascriptTranslationTestTrait;
  use ContextualLinkClickTrait;

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
    'field_ui',
    'layout_builder',
    'block',
    'node',
    'contextual',
  ];

  /**
   * Data provider for testLabelTranslation.
   */
  public static function providerLabelTranslation(): array {
    return [
      'install before' => [TRUE],
      'install after' => [FALSE],
    ];
  }

  /**
   * Tests that block labels can be translated.
   *
   * @dataProvider providerLabelTranslation
   */
  public function testLabelTranslation($install_before) {
    if ($install_before) {
      $this->container->get('module_installer')->install(['layout_builder_st']);
      $this->layoutBuilderSetup();
    }
    else {
      $this->layoutBuilderSetup();
      $this->container->get('module_installer')->install(['layout_builder_st']);
    }

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      // @todo should you need this permission? You don't actually save the
      //   entity translation because labels are stored with untranslated
      //   layout.
      //   'translate bundle_with_section_field node'.
    ]));

    // Add a new inline block to the original node.
    $this->drupalGet('node/1/layout');

    $this->clickContextualLink('.block-field-blocknodebundle-with-section-fieldbody', 'Configure');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $page->fillField('settings[label]', 'field label untranslated');
    $page->checkField('settings[label_display]');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $this->addBlock('Powered by Drupal', '.block-system-powered-by-block', TRUE, 'untranslated label');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->addressEquals('node/1');

    // Update the translations block label.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'untranslated label', 'label in translation');

    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('label in translation');
    $assert_session->pageTextNotContains('untranslated label');

    // Confirm that untranslated label is still used on default translation.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('untranslated label');
    $assert_session->pageTextNotContains('label in translation');

    // Update the translations block label.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();
    $this->updateBlockTranslation('.block-system-powered-by-block', 'untranslated label', 'label updated in translation', 'label in translation');

    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');
    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('label updated in translation');
    $assert_session->pageTextNotContains('label in translation');
  }

  /**
   * Setup for layout builder.
   *
   * This is extracted ouf from the setUp() method in
   * https://www.drupal.org/node/2946333 to test install the module before and
   * after enabling overrides on a bundle.
   */
  protected function layoutBuilderSetup() {
    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'new_revision' => TRUE,
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);
    // Adds a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the node type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'bundle_with_section_field', TRUE);

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
    ]));

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
  }

}

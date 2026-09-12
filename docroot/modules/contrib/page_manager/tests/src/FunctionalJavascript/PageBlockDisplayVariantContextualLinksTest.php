<?php

namespace Drupal\Tests\page_manager\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\page_manager\Traits\WebDriverFormSubmitTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the contextual links the placed blocks.
 *
 * @group page_manager
 */
class PageBlockDisplayVariantContextualLinksTest extends WebDriverTestBase {

  use WebDriverFormSubmitTrait;
  use ContextualLinkClickTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * Testing content block.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $contentBlock;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'contextual',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    BlockContentType::create(['id' => 'test_block_type', 'label' => 'test_block_type'])->save();
    // Newer core versions no longer guarantee the shared block body storage
    // exists before tests create a custom block type.
    if (!FieldStorageConfig::loadByName('block_content', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'block_content',
        'type' => 'text_with_summary',
      ])->save();
    }
    // The bundle-specific field config and displays should only be created
    // once, even if the shared storage was already provided elsewhere.
    if (!FieldConfig::loadByName('block_content', 'test_block_type', 'body')) {
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('block_content', 'body'),
        'bundle' => 'test_block_type',
        'label' => 'Body',
        'settings' => [
          'display_summary' => FALSE,
          'allowed_formats' => [],
        ],
      ])->save();

      $display_repository = $this->container->get('entity_display.repository');
      $display_repository->getFormDisplay('block_content', 'test_block_type')
        ->setComponent('body', [
          'type' => 'text_textarea_with_summary',
        ])
        ->save();
      $display_repository->getViewDisplay('block_content', 'test_block_type')
        ->setComponent('body', [
          'label' => 'hidden',
          'type' => 'text_default',
        ])
        ->save();
    }
    $this->contentBlock = BlockContent::create([
      'type' => 'test_block_type',
      'uuid' => '86ed355c-2ce2-4835-8e6e-baeb8227d724',
      'info' => 'Test block content',
      'body' => 'Test block content body',
    ]);
    $this->contentBlock->save();

    // Enabling the testing module only after the content block has been created
    // because the configuration installed by this module depends on this block.
    \Drupal::service('module_installer')
      ->install(['page_manager_contextual_links_test']);

    $administer_block_content = version_compare(\Drupal::VERSION, '10.0', '>') ? 'administer block content' : 'administer views';

    $this->drupalLogin($this->createUser([
      'access contextual links',
      'access user profiles',
      'administer blocks',
      'administer views',
      $administer_block_content,
    ]));
  }

  /**
   * Tests the contextual links the placed blocks.
   */
  public function testContextualLinks() {
    $this->drupalGet('page-manager-test/page-block-display-variant');
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    // Check that page titles and content are as expected.
    $assert_session->pageTextContains('Test custom block');
    $assert_session->pageTextContains('Test block content body');
    $assert_session->pageTextContains("Who's online");
    $assert_session->pageTextContains("There are currently 1 users online.");
    $page_url = $session->getCurrentUrl();

    // Check that it's possible to edit the block using the contextual link.
    $this->clickContextualLink('.block-block-content', 'Edit');
    $assert_session->fieldValueEquals('Block description', 'Test block content');
    $assert_session->fieldValueEquals('Body', 'Test block content body');

    // Check that the contextual link destination is valid.
    $session->getPage()->pressButton('Save');
    $this->waitForPage();
    $assert_session->pageTextContains('Test block content has been updated.');
    $this->assertEquals($page_url, $session->getCurrentUrl());

    // Check that is possible to edit the view using the contextual link.
    $this->clickContextualLink('.block-views', 'Edit view');

    // Check that the contextual link destination is valid.
    $session->getPage()->pressButton('Save');
    $this->waitForPage();
    $assert_session->pageTextContains("The view Who's online block has been saved.");
    $this->assertEquals($page_url, $session->getCurrentUrl());
  }

}

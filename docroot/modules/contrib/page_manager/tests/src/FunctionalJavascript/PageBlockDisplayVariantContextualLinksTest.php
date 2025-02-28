<?php

namespace Drupal\Tests\page_manager\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the contextual links the placed blocks.
 *
 * @group page_manager
 */
class PageBlockDisplayVariantContextualLinksTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

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

    BlockContentType::create(['id' => 'test_block_type'])->save();
    block_content_add_body_field('test_block_type');
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

    $this->drupalLogin($this->createUser([
      'access contextual links',
      'access user profiles',
      'administer blocks',
      'administer views',
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
    $assert_session->pageTextContains('Test block content has been updated.');
    $this->assertEquals($page_url, $session->getCurrentUrl());

    // Check that is possible to edit the view using the contextual link.
    $this->clickContextualLink('.block-views', 'Edit view');

    // Check that the contextual link destination is valid.
    $session->getPage()->pressButton('Save');
    $assert_session->pageTextContains("The view Who's online block has been saved.");
    $this->assertEquals($page_url, $session->getCurrentUrl());
  }

}

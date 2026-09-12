<?php

namespace Drupal\Tests\quickedit\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests quickedit.
 *
 * @group quickedit
 */
class FieldTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'contextual',
    'quickedit',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a text format and associate CKEditor5.
    $filtered_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 0,
    ]);
    $filtered_html_format->save();

    Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'blockQuote',
          ],
        ],
      ],
    ])->save();

    // Create note type with body field.
    $node_type = NodeType::create(['type' => 'page', 'name' => 'Page']);
    $node_type->save();
    node_add_body_field($node_type);

    $account = $this->drupalCreateUser([
      'access content',
      'administer nodes',
      'edit any page content',
      'use text format full_html',
      'access contextual links',
      'access in-place editing',
    ]);
    $this->drupalLogin($account);

  }

  /**
   * Tests that quickeditor works correctly for field with CKEditor5.
   */
  public function testFieldWithCkeditor() {
    $body_value = '<p>Dare to be wise</p>';
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page node',
      'body' => [
        'value' => $body_value,
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    $page = $this->getSession()->getPage();
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalGet('node/' . $node->id());

    // Wait "Quick edit" button for node.
    $assert->waitForElement('css', '[data-quickedit-entity-id="node/' . $node->id() . '"] .contextual .quickedit');
    // Click by "Quick edit".
    $this->clickContextualLink('[data-quickedit-entity-id="node/' . $node->id() . '"]', 'Quick edit');
    // Switch to body field.
    $page->find('css', '[data-quickedit-field-id="node/' . $node->id() . '/body/en/full"]')->click();
    // Wait and click by "Blockquote" button from editor for body field.
    $assert->waitForElementVisible('css', '.ck-button[data-cke-tooltip-text="Block quote"]')->click();
    // // Wait and click by "Save" button after body field was changed.
    $assert->waitForElementVisible('css', '.quickedit-toolgroup.ops [type="submit"][aria-hidden="false"]')->click();
    // // Wait until the save occurs and the editor UI disappears.
    $this->assertSession()->assertNoElementAfterWait('css', '.ck-button[data-cke-tooltip-text="Block quote"]');
    // // Ensure that the changes take effect.
    $assert->responseMatches("|<blockquote>\s*$body_value\s*</blockquote>|");
  }

}

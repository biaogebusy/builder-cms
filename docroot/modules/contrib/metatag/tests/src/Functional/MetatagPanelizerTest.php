<?php

namespace Drupal\Tests\metatag\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Verify that the JSON output from core works as intended.
 *
 * @group panelizer_metatag
 *
 * @deprecated in metatag:8.x-1.22 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/project/metatag/issues/3305580
 */
class MetatagPanelizerTest extends BrowserTestBase {

  // Contains helper methods.
  use MetatagHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Modules for core functionality.
    'node',
    'field',
    'field_ui',
    'user',

    // Contrib dependencies.
    'panelizer',
    'token',

    // This module.
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * Create an entity, view its JSON output, confirm Metatag data exists.
   */
  public function testPanelizerMetatagPreRender() {
    $title = 'Panelizer Metatag Test Title';
    $body = 'Testing JSON output for a content type';
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->createContentTypeNode($title, $body);
    $url = $node->toUrl();

    // Initiate session with a user who can manage metatags.
    $permissions = ['administer node display', 'administer meta tags'];
    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);

    // Load the node's page.
    $this->drupalGet('admin/structure/types/manage/metatag_test/display');
    $this->submitForm(
      ['panelizer[enable]' => TRUE],
      'Save'
    );

    $this->drupalGet('admin/structure/types/manage/metatag_test/display');
    $this->assertSession()->checkboxChecked('panelizer[enable]');

    $this->drupalGet($url);
    $this->assertSession()->elementContains('css', 'title', $title . ' | Drupal');
    $xpath = $this->xpath("//link[@rel='canonical']");
    self::assertEquals((string) $xpath[0]->getAttribute('href'), $url->toString());
    self::assertEquals(count($xpath), 1);
  }

}

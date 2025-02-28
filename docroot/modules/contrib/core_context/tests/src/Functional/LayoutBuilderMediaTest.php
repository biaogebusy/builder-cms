<?php

namespace Drupal\Tests\core_context\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests integration with the core Media and Layout Builder modules.
 *
 * @group core_context
 */
class LayoutBuilderMediaTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context',
    'field_ui',
    'layout_builder',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the layout of a media type can be edited.
   */
  public function testMediaLayout(): void {
    $media_type = $this->createMediaType('image')->id();

    $this->drupalLogin($this->rootUser);
    $this->drupalGet("/admin/structure/media/manage/$media_type/display");
    $page = $this->getSession()->getPage();
    $page->checkField('Use Layout Builder');
    $page->pressButton('Save');
    $page->clickLink('Manage layout');
    $this->assertSession()->statusCodeEquals(200);
  }

}

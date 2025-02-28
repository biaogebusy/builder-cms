<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group panelizer
 */
class PanelizerAddDefaultLinkTest extends BrowserTestBase {

  use PanelizerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'panels_ipe',
    'panelizer',
  ];

  /**
   * Confirm a content type can be panelized and unpanelized.
   */
  public function test() {
    $assert_session = $this->assertSession();

    // Place the local actions block in the theme so that we can assert the
    // presence of local actions and such.
    $this->drupalPlaceBlock('local_actions_block', [
      'region' => 'content',
      'theme' => \Drupal::theme()->getActiveTheme()->getName(),
    ]);

    $content_type = 'page';

    // Log in the user.
    $this->loginUser1();

    // Create the content type.
    $this->drupalCreateContentType(['type' => $content_type, 'name' => 'Page']);

    $this->container->get('panelizer')
      ->setPanelizerSettings('node', 'page', 'default', [
        'enable' => TRUE,
        'allow' => FALSE,
        'custom' => FALSE,
        'default' => 'default',
      ]);

    $this->drupalGet('/admin/structure/types/manage/page/display');

    // Confirm that the content type is now panelized.
    $assert_session->linkNotExists('Add a new Panelizer default display');

    // Un-panelize the content type.
    $this->unpanelize($content_type);

    // Confirm that the content type is no longer panelized.
    $assert_session->linkNotExists('Add a new Panelizer default display');
  }

}

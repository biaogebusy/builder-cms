<?php

namespace Drupal\Tests\panels\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests validating config in the Panels display manager.
 *
 * @group panels
 */
class PanelsConfigSchemaTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['panels', 'block', 'node', 'user'];

  /**
   * @var \Drupal\panels\PanelsDisplayManagerInterface
   */
  protected $panelsManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->panelsManager = \Drupal::service('panels.display_manager');
  }

  /**
   * Tests whether the Panels display config schema is valid.
   */
  public function testPanelsConfigSchema() {
    $panels_display = $this->panelsManager->createDisplay();

    // Add a block.
    $panels_display->addBlock([
      'id' => 'entity_view:node',
      'label' => 'View the node',
      'provider' => 'page_manager',
      'label_display' => 'visible',
      'view_mode' => 'default',
      'region' => 'content',
    ]);

    $config = $this->panelsManager->exportDisplay($panels_display);
    // This will throw an exception if it doesn't validate.
    $new_panels_display = $this->panelsManager->importDisplay($config, TRUE);

    $this->assertEquals($panels_display->getConfiguration(), $new_panels_display->getConfiguration());
  }

}

<?php

namespace Drupal\Tests\fivestar\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the operation of hook_fivestar_widgets_alter().
 *
 * @group Fivestar
 */
class HookFivestarWidgetsAlterTest extends KernelTestBase {

  /**
   * The Fivestar widget manager.
   *
   * @var \Drupal\fivestar\WidgetManager
   */
  protected $widgetManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'votingapi',
    'fivestar',
    'fivestar_widget_provider',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->widgetManager = $this->container->get('fivestar.widget_manager');
  }

  /**
   * Tests that fivestar_widget_provider_fivestar_widgets_alter() was called.
   */
  public function testWidgetAlter(): void {
    // Invoke the hook and collect all defined and altered widgets.
    $widgets = $this->widgetManager->getWidgets();

    // Verify the label of the "Basic" widget was changed to "Altered".
    $this->assertArrayHasKey('basic', $widgets);
    $this->assertEquals('Altered', $widgets['basic']['label']);
  }

}

<?php

namespace Drupal\Tests\fivestar\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the operation of hook_fivestar_widgets().
 *
 * @group Fivestar
 */
class HookFivestarWidgetsTest extends KernelTestBase {

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
   * Tests finding widgets defined by hook_fivestar_widgets().
   */
  public function testWidgetDiscovery(): void {
    $expected = [
      // Awesome Stars is defined in the fivestar_widget_provider module.
      'awesome' => [
        'library' => 'fivestar_widget_provider/awesome',
        'label' => 'Awesome Stars',
      ],
      // Cools Stars is defined in the fivestar_widget_provider module.
      'cool' => [
        'library' => 'fivestar_widget_provider/cool',
        'label' => 'Cool Stars',
      ],
    ];

    // Invoke the hook and collect all defined widgets.
    $widgets = $this->widgetManager->getWidgets();

    // Verify "Awesome Stars" was discovered.
    $this->assertArrayHasKey('awesome', $widgets);
    $this->assertEquals($expected['awesome']['label'], $widgets['awesome']['label']);
    $this->assertEquals($expected['awesome']['library'], $widgets['awesome']['library']);

    // Verify "Cool Stars" was discovered.
    $this->assertArrayHasKey('cool', $widgets);
    $this->assertEquals($expected['cool']['label'], $widgets['cool']['label']);
    $this->assertEquals($expected['cool']['library'], $widgets['cool']['library']);
  }

}

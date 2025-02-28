<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\panelizer\LayoutBuilderMigration;
use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\panelizer\LayoutBuilderMigration
 *
 * @group panelizer
 */
class LayoutBuilderMigrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'node',
    'panelizer',
  ];

  /**
   * Tests migrating an entity with an invalid layout plugin ID.
   */
  public function testMigrateEntityWithInvalidLayout() {
    $this->drupalCreateContentType(['type' => 'page']);

    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = $this->container->get('panelizer');
    $panelizer->setPanelizerSettings('node', 'page', 'full', [
      'enable' => TRUE,
      'allow' => FALSE,
      'custom' => TRUE,
      'default' => 'default',
    ]);

    $node = $this->drupalCreateNode(['type' => 'page']);
    $display = $panelizer->getPanelsDisplay($node, 'full');
    $panelizer->setPanelsDisplay($node, 'full', NULL, $display);

    $layout_manager = $this->container->get('plugin.manager.core.layout');
    $this->container->set('plugin.manager.core.layout', new class (
      $this->container->get('container.namespaces'),
      $this->container->get('cache.backend.memory')->get('foo'),
      $this->container->get('module_handler'),
      $this->container->get('theme_handler')
    ) extends LayoutPluginManager {

      /**
       * {@inheritdoc}
       */
      public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
        if ($plugin_id === 'onecol') {
          $plugin_id = 'layout_onecol';
        }
        return parent::getDefinition($plugin_id, $exception_on_invalid);
      }

    });

    $configuration = $node->panelizer->panels_display;
    $configuration['layout'] = 'onecol';
    $node->panelizer->panels_display = $configuration;
    $node->save();

    $this->container->set('plugin.manager.core.layout', $layout_manager);

    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    LayoutBuilderMigration::processEntity('node', $node->getRevisionId());
  }

}

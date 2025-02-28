<?php

namespace Drupal\Tests\entity_print\Unit;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\entity_print\Asset\AssetCollector;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Test the asset collector service.
 *
 * @group entity_print
 */
class AssetCollectorTest extends UnitTestCase {

  /**
   * CSS Alter event should always fire, even when no entries in the theme file.
   */
  public function testEventAlwaysFires() {
    $event_dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $event_dispatcher->dispatch(Argument::cetera())->shouldBeCalled();
    $asset_collector = new AssetCollector($this->getThemeHandlerMock()->reveal(), $this->getThemeExtensionMock()->reveal(), $event_dispatcher->reveal());
    $this->assertEquals([], $asset_collector->getCssLibraries([]));
  }

  /**
   * Test that we can alter the CSS using the event.
   */
  public function testAlterCss() {
    $event_dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $event_dispatcher->dispatch(Argument::cetera())->will(function ($args) {
      // Argument 0 is the PrintCssAlterEvent.
      $args[0]->getBuild()[] = 'my_theme/my_css';
      return $args[0];
    });
    $asset_collector = new AssetCollector($this->getThemeHandlerMock()->reveal(), $this->getThemeExtensionMock()->reveal(), $event_dispatcher->reveal());
    $this->assertEquals(['my_theme/my_css'], $asset_collector->getCssLibraries([]));
  }

  /**
   * Gets the theme handler mock.
   */
  protected function getThemeHandlerMock() {
    $theme = $this->prophesize(Extension::class);
    $theme->getPathname()->willReturn('info_file_path');
    $theme->getName()->willReturn('entity_print_test_theme');
    $theme_handler = $this->prophesize(ThemeHandlerInterface::class);
    $theme_handler->getDefault()->willReturn('default_theme');
    $theme_handler->getTheme('default_theme')->willReturn($theme);
    return $theme_handler;
  }

  /**
   * Gets a theme extension list mock.
   */
  protected function getThemeExtensionMock() {
    $theme_extension = $this->prophesize(ThemeExtensionList::class);
    $theme_extension->get('entity_print_test_theme')->willReturn((object) ['info' => []]);
    return $theme_extension;
  }

}

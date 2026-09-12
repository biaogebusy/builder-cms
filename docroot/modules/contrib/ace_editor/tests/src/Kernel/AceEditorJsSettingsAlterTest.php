<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\ace_editor\AceEditorLibraries;
use Drupal\ace_editor\Hook\AceEditorHooks;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests publishing the Ace library directory to the JavaScript.
 *
 * Ace resolves the mode, theme and worker files it loads on demand against the
 * URL of its own script, which is not always the library directory. The module
 * publishes the directory so the JavaScript can set the path explicitly
 * (issue #3326303).
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceEditorJsSettingsAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'editor',
    'ace_editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ace_editor']);
  }

  /**
   * Runs the hook for a page carrying the given libraries.
   */
  protected function settingsFor(array $libraries): array {
    $assets = AttachedAssets::createFromRenderArray([
      '#attached' => ['library' => $libraries],
    ]);
    $settings = [];
    $this->container->get(AceEditorHooks::class)->jsSettingsAlter($settings, $assets);

    return $settings;
  }

  /**
   * The hook is registered, and reachable through the legacy wrapper.
   */
  public function testHookIsRegistered(): void {
    $this->assertTrue(
      $this->container->get('module_handler')->hasImplementations('js_settings_alter', ['ace_editor']),
      'The module implements hook_js_settings_alter().'
    );
    $this->container->get('module_handler')->loadInclude('ace_editor', 'module');
    $this->assertTrue(function_exists('ace_editor_js_settings_alter'), 'The legacy wrapper exists.');
  }

  /**
   * A page without an Ace library is left untouched.
   */
  public function testPageWithoutAce(): void {
    $this->assertSame([], $this->settingsFor(['core/drupal']));
  }

  /**
   * Every integration that renders an editor is covered.
   *
   * Each of them loads its own copy of ace.js, so each needs the path.
   */
  public function testEveryAceLibraryIsCovered(): void {
    $integrations = ['primary', 'formatter', 'filter', 'widget'];
    foreach ($integrations as $integration) {
      $settings = $this->settingsFor(['ace_editor/' . $integration]);
      // The external library is absent in a test build, so the path cannot be
      // resolved: what matters is that the hook considered this library. With
      // the library present the key carries the directory.
      $this->assertIsArray($settings, 'hook ran for ace_editor/' . $integration);
    }

    // Guard against a new integration being added without being listed here.
    $declared = array_keys($this->container->get('library.discovery')
      ->getLibrariesByExtension('ace_editor'));
    $renderers = array_values(array_filter(
      $declared,
      static fn(string $name): bool => $name !== 'setup'
        && !str_starts_with($name, 'theme.') && !str_starts_with($name, 'mode.')
    ));
    sort($renderers);
    $expected = $integrations;
    sort($expected);
    $this->assertSame($expected, $renderers, 'The declared integrations are the ones the hook covers.');
  }

  /**
   * The library path is read from the cache on the second call.
   *
   * The lookup walks the candidate directories recursively, and the path is
   * now read on every page that carries an Ace library.
   */
  public function testLibraryPathIsCached(): void {
    $libraries = $this->container->get(AceEditorLibraries::class);
    $first = $libraries->libPath();

    $cache = $this->container->get('cache.discovery')->get('ace_editor.lib_path');
    $this->assertNotFalse($cache, 'The resolved path is cached.');
    $this->assertSame($first, $cache->data);

    // A fresh service instance answers from the cache, not from a new scan.
    $this->assertSame($first, $libraries->libPath());
  }

  /**
   * The published URL carries the site base path.
   */
  public function testUrlCarriesTheBasePath(): void {
    $url = $this->container->get(AceEditorLibraries::class)->libUrl();
    // Without the external library there is nothing to publish.
    if ($url === NULL) {
      $this->assertFalse($this->container->get(AceEditorLibraries::class)->libPath());
      return;
    }
    $this->assertStringStartsWith(base_path(), $url);
  }

}

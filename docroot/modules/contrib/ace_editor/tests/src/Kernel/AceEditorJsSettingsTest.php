<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings handed to the editor JavaScript.
 *
 * The configuration form nests its values under "fieldset", while
 * configuration created programmatically - by a recipe, a config import, or an
 * older release - keeps them at the top level. Both shapes must produce
 * usable settings: a NULL reaches the JavaScript as format.editorSettings and
 * the editor never attaches.
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
#[RunTestsInSeparateProcesses]
class AceEditorJsSettingsTest extends KernelTestBase {

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
    $this->installConfig(['ace_editor', 'filter']);

    FilterFormat::create([
      'format' => 'ace_test_format',
      'name' => 'Ace test format',
    ])->save();
  }

  /**
   * Returns the JS settings for an editor with the given stored settings.
   */
  protected function jsSettingsFor(array $settings): array {
    $editor = Editor::create([
      'format' => 'ace_test_format',
      'editor' => 'ace_editor',
      'settings' => $settings,
    ]);
    $editor->save();

    $plugin = $this->container->get('plugin.manager.editor')
      ->createInstance('ace_editor');
    return $plugin->getJsSettings($editor);
  }

  /**
   * Settings nested under "fieldset" (saved through the form) are returned.
   */
  public function testNestedFieldsetSettings(): void {
    $js = $this->jsSettingsFor([
      'fieldset' => [
        'theme' => 'monokai',
        'syntax' => 'php',
        'height' => '400px',
      ],
    ]);

    $this->assertSame('monokai', $js['theme']);
    $this->assertSame('php', $js['syntax']);
    $this->assertSame('400px', $js['height']);
  }

  /**
   * Flat settings (recipe, config import, older release) are returned.
   */
  public function testFlatSettings(): void {
    $js = $this->jsSettingsFor([
      'theme' => 'twilight',
      'syntax' => 'yaml',
    ]);

    $this->assertSame('twilight', $js['theme']);
    $this->assertSame('yaml', $js['syntax']);
  }

  /**
   * Empty stored settings still produce a usable set of defaults.
   *
   * This is the case that used to hand NULL to the JavaScript.
   */
  public function testEmptySettingsFallBackToDefaults(): void {
    $js = $this->jsSettingsFor([]);

    $this->assertIsArray($js);
    $this->assertNotEmpty($js);
    $this->assertArrayHasKey('theme', $js);
    $this->assertArrayHasKey('syntax', $js);
  }

  /**
   * Partial settings are completed from the module defaults.
   */
  public function testPartialSettingsAreCompleted(): void {
    $js = $this->jsSettingsFor(['fieldset' => ['theme' => 'github']]);

    $this->assertSame('github', $js['theme']);
    $this->assertArrayHasKey('syntax', $js, 'The missing syntax setting comes from the defaults.');
  }

  /**
   * Returns the libraries for an editor with the given stored settings.
   */
  protected function librariesFor(array $settings): array {
    $editor = Editor::load('ace_test_format') ?: Editor::create([
      'format' => 'ace_test_format',
      'editor' => 'ace_editor',
    ]);
    $editor->setSettings($settings);
    $editor->save();

    return $this->container->get('plugin.manager.editor')
      ->createInstance('ace_editor')
      ->getLibraries($editor);
  }

  /**
   * Reports every requested library as available.
   *
   * The Ace library is an external asset that a test build does not carry, so
   * the theme and mode libraries are built from nothing and the real discovery
   * reports them missing. Selecting a theme is only observable once they are
   * there.
   */
  protected function assumeLibrariesExist(): void {
    $this->container->set('library.discovery', new class implements LibraryDiscoveryInterface {

      /**
       * {@inheritdoc}
       */
      public function getLibrariesByExtension($extension) {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function getLibraryByName($extension, $name) {
        return ['js' => []];
      }

      /**
       * {@inheritdoc}
       */
      public function clearCachedDefinitions() {
      }

    });
  }

  /**
   * The libraries follow the theme and syntax of both settings shapes.
   *
   * Reading the nested shape unconditionally raised two warnings and a trim()
   * deprecation for every flat configuration.
   */
  public function testLibrariesForBothSettingsShapes(): void {
    $this->assumeLibrariesExist();

    $nested = $this->librariesFor(['fieldset' => ['theme' => 'monokai', 'syntax' => 'php']]);
    $this->assertContains('ace_editor/primary', $nested);
    $this->assertContains('ace_editor/theme.monokai', $nested);
    $this->assertContains('ace_editor/mode.php', $nested);

    $flat = $this->librariesFor(['theme' => 'twilight', 'syntax' => 'yaml']);
    $this->assertContains('ace_editor/theme.twilight', $flat);
    $this->assertContains('ace_editor/mode.yaml', $flat);
  }

  /**
   * Reading the libraries raises no warning for any settings shape.
   *
   * The editors stay unsaved: a NULL "fieldset" is not valid against the
   * configuration schema, but it still has to be survivable at runtime.
   */
  public function testLibrariesRaiseNoErrors(): void {
    $plugin = $this->container->get('plugin.manager.editor')
      ->createInstance('ace_editor');

    $raised = [];
    set_error_handler(function ($level, $message) use (&$raised) {
      $raised[] = $message;
      return TRUE;
    });

    try {
      foreach ([['theme' => 'twilight'], [], ['fieldset' => NULL]] as $settings) {
        $editor = Editor::create([
          'format' => 'ace_test_format',
          'editor' => 'ace_editor',
          'settings' => $settings,
        ]);
        $this->assertContains('ace_editor/primary', $plugin->getLibraries($editor));
      }
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame([], $raised);
  }

  /**
   * Empty stored settings resolve to the configured defaults.
   */
  public function testLibrariesForEmptySettings(): void {
    $this->assumeLibrariesExist();
    $libraries = $this->librariesFor([]);

    $this->assertContains('ace_editor/primary', $libraries);
    $this->assertContains('ace_editor/theme.' . $this->config('ace_editor.settings')->get('theme'), $libraries);
    $this->assertContains('ace_editor/mode.' . $this->config('ace_editor.settings')->get('syntax'), $libraries);
  }

  /**
   * Saved editor settings do not carry the form's option lists.
   *
   * Drupal merges the plugin defaults into the saved entity, so returning the
   * whole module configuration used to write the 34 theme and 140 syntax
   * options into every editor.editor.* configuration.
   */
  public function testOptionListsAreNotSaved(): void {
    $editor = Editor::create([
      'format' => 'ace_test_format',
      'editor' => 'ace_editor',
      'settings' => ['fieldset' => ['theme' => 'chrome']],
    ]);
    $editor->save();

    $saved = $this->config('editor.editor.ace_test_format')->get('settings');
    $this->assertArrayNotHasKey('theme_list', $saved);
    $this->assertArrayNotHasKey('syntax_list', $saved);
  }

}

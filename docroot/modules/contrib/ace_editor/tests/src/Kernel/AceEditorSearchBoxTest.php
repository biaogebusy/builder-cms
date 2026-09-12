<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\ace_editor\AceEditorLibraries;
use Drupal\ace_editor\Hook\AceEditorHooks;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the Ace search box extension is attached for editing.
 *
 * Coverage for issue #3473555: the Find (Ctrl-F) and Replace (Ctrl-H)
 * shortcuts do nothing unless ext-searchbox.js is loaded. Only the editing
 * library needs it - the formatter and the filter render read-only code.
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceEditorSearchBoxTest extends KernelTestBase {

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
   * Returns the JavaScript assets the alter hook adds to a library.
   *
   * @param \Drupal\ace_editor\AceEditorLibraries $discovery
   *   The library discovery to drive the hook with.
   *
   * @return string[]
   *   The asset paths, keyed by library name.
   */
  protected function alteredAssets(AceEditorLibraries $discovery): array {
    $libraries = [
      'primary' => ['js' => []],
      'formatter' => ['js' => []],
      'filter' => ['js' => []],
    ];

    (new AceEditorHooks($discovery))->libraryInfoAlter($libraries, 'ace_editor');

    $assets = [];
    foreach ($libraries as $name => $library) {
      $assets[$name] = array_keys($library['js']);
    }
    return $assets;
  }

  /**
   * Returns a library discovery that reports a known path.
   *
   * @param string|false $path
   *   The path libPath() should report.
   */
  protected function discoveryReporting(string|false $path): AceEditorLibraries {
    return new class(
      $this->container->get('file_system'),
      $this->container->get('extension.list.module'),
      $this->container->get('extension.list.profile'),
      $this->container->get('config.factory'),
      NULL,
      $this->container->get('cache.discovery'),
      $path,
    ) extends AceEditorLibraries {

      public function __construct(
        $file_system,
        $module_extension_list,
        $profile_extension_list,
        $config_factory,
        ?string $install_profile,
        $cache_discovery,
        protected string|false $reportedPath = FALSE,
      ) {
        parent::__construct($file_system, $module_extension_list, $profile_extension_list, $config_factory, $install_profile, $cache_discovery);
      }

      /**
       * {@inheritdoc}
       */
      public function libPath(): string|false {
        return $this->reportedPath;
      }

    };
  }

  /**
   * Nothing is attached while the external library is missing.
   *
   * The guard is what is under test: the hook adds no asset path it cannot
   * serve. The missing-library condition is created here rather than read off
   * the build, because the module requires vardot/ace with Composer and the
   * library is present in any built site.
   */
  public function testNoAssetsWithoutTheLibrary(): void {
    $assets = $this->alteredAssets($this->discoveryReporting(FALSE));

    $this->assertSame([], $assets['primary']);
    $this->assertSame([], $assets['formatter']);
    $this->assertSame([], $assets['filter']);
  }

  /**
   * The editing library asks for the search box extension.
   */
  public function testEditingLibraryRequestsTheSearchBox(): void {
    // Drive the discovery with a known path, so the assertion is about which
    // library asks for the extension and not about where Ace happens to sit.
    $assets = $this->alteredAssets($this->discoveryReporting('/libraries/ace/'));

    $this->assertContains('/libraries/ace/ext-searchbox.js', $assets['primary']);
    $this->assertNotContains('/libraries/ace/ext-searchbox.js', $assets['formatter']);
    $this->assertNotContains('/libraries/ace/ext-searchbox.js', $assets['filter']);
  }

}

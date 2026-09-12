<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\ace_editor\AceEditorLibraries;
use Drupal\ace_editor\Hook\AceEditorHooks;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the library discovery service and the hook implementations.
 *
 * Coverage for issue #3594544: the procedural helpers moved into the
 * AceEditorLibraries service and the AceEditorHooks class, and the legacy
 * function wrappers must keep working. These tests assert the "library
 * missing" behaviour: the discovery returns FALSE and the hooks degrade
 * without errors instead of building broken asset paths.
 *
 * The condition is created deliberately, with a file system that finds
 * nothing. It used to be enough to rely on the build, because the Ace library
 * was an external asset a test build did not have. The module requires
 * vardot/ace with Composer now, so the library IS present in any built site
 * and a test that reads the real /libraries directory would assert the
 * opposite of what it claims to cover.
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
#[RunTestsInSeparateProcesses]
class AceEditorLibrariesTest extends KernelTestBase {

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
   * The services are wired and injectable.
   */
  public function testServicesAreRegistered(): void {
    $this->assertInstanceOf(AceEditorLibraries::class, $this->container->get(AceEditorLibraries::class));
    $this->assertInstanceOf(AceEditorHooks::class, $this->container->get(AceEditorHooks::class));
  }

  /**
   * Returns a library service that finds no Ace library on disk.
   *
   * The real libPath() logic runs; only the directory scan is stubbed, so the
   * guard being tested is the module's own and not the test's.
   */
  protected function librariesWithoutTheLibrary(): AceEditorLibraries {
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('scanDirectory')->willReturn([]);

    return new AceEditorLibraries(
      $file_system,
      $this->container->get('extension.list.module'),
      $this->container->get('extension.list.profile'),
      $this->container->get('config.factory'),
      NULL,
      // A cache of its own: the shared discovery cache may already hold the
      // path resolved from the library the site really has.
      new MemoryBackend(new Time()),
    );
  }

  /**
   * Library discovery reports FALSE when the external asset is absent.
   */
  public function testLibPathWithoutTheLibrary(): void {
    $libraries = $this->librariesWithoutTheLibrary();
    $this->assertFalse($libraries->libPath());

    // The legacy procedural wrapper returns whatever the service returns.
    $this->container->set(AceEditorLibraries::class, $libraries);
    $this->container->get('module_handler')->loadInclude('ace_editor', 'module');
    $this->assertFalse(ace_editor_lib_path());
  }

  /**
   * The build hook returns no dynamic libraries when the asset is absent.
   */
  public function testLibraryInfoBuildWithoutTheLibrary(): void {
    $hooks = new AceEditorHooks($this->librariesWithoutTheLibrary());
    $this->assertSame([], $hooks->libraryInfoBuild());
  }

  /**
   * The build hook returns a library per mode and theme when Ace is present.
   *
   * The counterpart of the test above, and the case a Composer-built site is
   * actually in: vardot/ace ships the minified no-conflict build, so the mode-
   * and theme- files are on disk and each becomes its own Drupal library.
   */
  public function testLibraryInfoBuildWithTheLibrary(): void {
    $libraries = $this->container->get(AceEditorLibraries::class);
    if (!$libraries->libPath()) {
      $this->markTestSkipped('The Ace library is not installed in this build.');
    }

    $built = $this->container->get(AceEditorHooks::class)->libraryInfoBuild();
    $this->assertNotEmpty($built, 'A mode or theme library is built from the Ace library on disk.');
    foreach ($built as $name => $library) {
      $this->assertMatchesRegularExpression('/^(mode|theme)\./', $name);
      $this->assertNotEmpty($library['js']);
    }
  }

  /**
   * The alter hook leaves other modules' libraries untouched.
   */
  public function testLibraryInfoAlterIgnoresOtherExtensions(): void {
    $libraries = ['primary' => ['js' => []]];
    $original = $libraries;
    $this->container->get(AceEditorHooks::class)->libraryInfoAlter($libraries, 'system');
    $this->assertSame($original, $libraries, 'Libraries of other extensions are not altered.');
  }

  /**
   * The help hook returns the module help text for its own route.
   */
  public function testHelpText(): void {
    $hooks = $this->container->get(AceEditorHooks::class);
    $route_match = $this->container->get('current_route_match');
    $help = (string) $hooks->help('help.page.ace_editor', $route_match);
    $this->assertStringContainsString('code editor written in JavaScript', $help);
    $this->assertNull($hooks->help('help.page.system', $route_match));
  }

}

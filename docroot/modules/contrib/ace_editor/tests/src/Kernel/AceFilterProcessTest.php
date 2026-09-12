<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests processing <ace> snippets, with and without attributes.
 *
 * A tag carrying no attributes is the documented basic usage, and the
 * attributes of one tag are read for every tag on the page, so both shapes
 * have to process without raising anything.
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceFilterProcessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
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
   * Runs the filter over a text, collecting anything PHP raises.
   *
   * @param string $text
   *   The text to process.
   *
   * @return array
   *   The processed markup under "markup", and the raised messages under
   *   "raised".
   */
  protected function process(string $text): array {
    $filter = $this->container->get('plugin.manager.filter')
      ->createInstance('ace_filter');

    $raised = [];
    set_error_handler(function ($level, $message) use (&$raised) {
      $raised[] = $message;
      return TRUE;
    });

    try {
      $result = $filter->process($text, 'en');
      $markup = (string) $result->getProcessedText();
      $attachments = $result->getAttachments();
    }
    finally {
      restore_error_handler();
    }

    return [
      'markup' => $markup,
      'raised' => $raised,
      'instances' => $attachments['drupalSettings']['ace_filter']['instances'] ?? [],
      'libraries' => $attachments['library'] ?? [],
    ];
  }

  /**
   * Each tag keeps its own theme/mode library; none is dropped by a later tag.
   */
  public function testEveryTagKeepsItsLibraries(): void {
    // The library must exist for the filter to attach it.
    $this->container->set('library.discovery', new class {

      /**
       * {@inheritdoc}
       */
      public function getLibraryByName($extension, $name) {
        return ['js' => []];
      }

    });

    $result = $this->process('<ace theme="twilight">a</ace><ace theme="cobalt" syntax="css">b</ace>');

    $this->assertContains('ace_editor/theme.twilight', $result['libraries']);
    $this->assertContains('ace_editor/theme.cobalt', $result['libraries']);
    $this->assertContains('ace_editor/mode.css', $result['libraries']);
    // Only this call's snippets are attached, not a cumulative page snapshot.
    $this->assertCount(2, $result['instances']);
  }

  /**
   * A tag without attributes is replaced and raises nothing.
   */
  public function testBareTag(): void {
    $result = $this->process('<p>Before</p><ace>const value = 1;</ace>');

    $this->assertSame([], $result['raised']);
    $this->assertStringContainsString('ace-editor-inline', $result['markup']);
    $this->assertStringNotContainsString('<ace>', $result['markup']);
  }

  /**
   * A tag with attributes keeps working, and its attributes are applied.
   */
  public function testTagWithAttributes(): void {
    $result = $this->process('<ace theme="twilight" syntax="php">$a = 1;</ace>');

    $this->assertSame([], $result['raised']);
    $this->assertStringContainsString('ace-editor-inline', $result['markup']);

    $last = end($result['instances']);
    $this->assertSame('twilight', $last['settings']['theme']);
    $this->assertSame('php', $last['settings']['syntax']);
  }

  /**
   * Several tags on one page are all replaced.
   */
  public function testBareAndAttributedTagsTogether(): void {
    $result = $this->process(
      '<ace>first</ace><p>between</p><ace syntax="css">.a { color: red; }</ace>'
    );

    $this->assertSame([], $result['raised']);
    $this->assertSame(2, substr_count($result['markup'], 'ace-editor-inline'));
    $this->assertStringNotContainsString('<ace', $result['markup']);
  }

  /**
   * Text without any tag is returned untouched.
   */
  public function testTextWithoutAnyTag(): void {
    $result = $this->process('<p>Nothing to highlight.</p>');

    $this->assertSame([], $result['raised']);
    $this->assertStringContainsString('Nothing to highlight.', $result['markup']);
    $this->assertStringNotContainsString('ace-editor-inline', $result['markup']);
    $this->assertSame([], $result['instances']);
  }

  /**
   * Entity-encoded markup outside a tag is never decoded to live markup.
   *
   * A previous filter (for example "Limit allowed HTML tags") leaves markup
   * entity-encoded; decoding the whole text here would return it as live HTML,
   * a stored XSS.
   */
  public function testEntityEncodedMarkupIsNotDecoded(): void {
    $result = $this->process('&lt;script&gt;alert(1)&lt;/script&gt;');

    $this->assertSame([], $result['raised']);
    $this->assertStringNotContainsString('<script>', $result['markup']);
    $this->assertStringContainsString('&lt;script&gt;', $result['markup']);
  }

  /**
   * The snippet content inside a tag is decoded for the editor.
   */
  public function testTagContentIsDecoded(): void {
    $result = $this->process('<ace>&lt;div&gt;x&lt;/div&gt;</ace>');

    $this->assertSame([], $result['raised']);
    $this->assertStringContainsString('ace-editor-inline', $result['markup']);
    $last = end($result['instances']);
    $this->assertStringContainsString('<div>x</div>', $last['content']);
  }

}

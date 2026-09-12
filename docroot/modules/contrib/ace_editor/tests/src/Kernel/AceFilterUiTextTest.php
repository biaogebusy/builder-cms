<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\ace_editor\Hook\AceEditorHooks;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the filter's user interface text survives translation import.
 *
 * Coverage for issue #3177428: a raw <ace> tag in a translatable string is
 * rejected by the translation import pipeline, so the string is skipped and
 * its translations are silently dropped. Entity-encoding the tag keeps the
 * source string importable and displays it as readable text.
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceFilterUiTextTest extends KernelTestBase {

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
   * Returns the filter plugin definition.
   */
  protected function filterDefinition(): array {
    return $this->container->get('plugin.manager.filter')
      ->getDefinition('ace_filter');
  }

  /**
   * The rendered description shows the tags as readable text.
   *
   * The source string carries no HTML at all: the tags arrive as placeholder
   * values, which Drupal escapes when the string is rendered, so the markup
   * carries the escaped form and the reader sees the tag.
   */
  public function testRenderedDescriptionShowsTheTags(): void {
    $description = (string) $this->filterDefinition()['description'];

    $this->assertStringNotContainsString('<ace>', $description);
    $this->assertStringNotContainsString('</ace>', $description);
    $this->assertStringContainsString('&lt;ace&gt;', $description);
    $this->assertStringContainsString('&lt;/ace&gt;', $description);
    // Guards against an accidental truncation of the string.
    $this->assertStringContainsString('tags to show it with syntax highlighting', $description);
  }

  /**
   * The translatable source string contains no HTML.
   *
   * This is what the translation import pipeline reads: a source string with
   * HTML that is not allowed is rejected, so the string is skipped and its
   * translations are silently dropped (issue #3177428).
   */
  public function testSourceStringContainsNoHtml(): void {
    $description = $this->filterDefinition()['description'];
    $this->assertInstanceOf(TranslatableMarkup::class, $description);

    $source = $description->getUntranslatedString();
    $this->assertDoesNotMatchRegularExpression('#</?[a-zA-Z]#', $source, 'The source string carries no HTML tag.');
    $this->assertStringNotContainsString('&lt;', $source, 'The source string carries no HTML entity either.');
    $this->assertStringContainsString('@open', $source, 'The tags arrive as placeholders.');
  }

  /**
   * The description survives admin filtering unchanged.
   *
   * This is the assertion that reproduces the reported symptom: a string the
   * filter alters is a string the translation import pipeline rejects.
   */
  public function testDescriptionSurvivesAdminFiltering(): void {
    $description = (string) $this->filterDefinition()['description'];
    $this->assertSame($description, Xss::filterAdmin($description));
  }

  /**
   * No user interface string of the filter carries a raw HTML tag.
   */
  public function testUiStringsCarryNoRawTags(): void {
    $definition = $this->filterDefinition();
    foreach (['title', 'description'] as $key) {
      $value = $definition[$key];
      $source = $value instanceof TranslatableMarkup ? $value->getUntranslatedString() : (string) $value;
      $this->assertDoesNotMatchRegularExpression('#</?[a-zA-Z]#', $source, sprintf('The filter %s carries no raw HTML tag.', $key));
    }
  }

  /**
   * The module help text keeps its markup outside translatable strings.
   */
  public function testHelpListMarkupIsOutsideTranslatableStrings(): void {
    $help = (string) $this->container->get(AceEditorHooks::class)
      ->help('help.page.ace_editor', $this->container->get('current_route_match'));

    // The list renders as one well-formed list, not two single-item lists.
    $this->assertSame(1, substr_count($help, '<ul>'), 'The supported forms render as a single list.');
    $this->assertStringContainsString('<li>node edit forms, including summary</li>', $help);
    $this->assertStringContainsString('<li>blocks edit forms</li>', $help);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Unit;

use Drupal\ace_editor\AceEditorLibraryPreference;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which Ace build is preferred when several are present.
 *
 * Coverage for issue #3322712. The ace-builds package ships all four builds,
 * so the ranking decides what every site loads: minified saves transfer, and
 * the no-conflict builds avoid defining the global AMD functions that clash
 * with another loader on the page.
 *
 * @group ace_editor
 * @coversDefaultClass \Drupal\ace_editor\AceEditorLibraryPreference
 */
#[Group('ace_editor')]
class AceEditorLibraryPreferenceTest extends UnitTestCase {

  /**
   * Candidate sets and the build that must win.
   */
  public static function candidates(): array {
    $base = '/var/www/html/libraries/ace-builds';
    return [
      'all four builds' => [
        [
          "$base/src/ace.js",
          "$base/src-min/ace.js",
          "$base/src-noconflict/ace.js",
          "$base/src-min-noconflict/ace.js",
        ],
        "$base/src-min-noconflict/ace.js",
      ],
      'no-conflict beats minified' => [
        ["$base/src-min/ace.js", "$base/src-noconflict/ace.js"],
        "$base/src-noconflict/ace.js",
      ],
      'minified beats plain source' => [
        ["$base/src/ace.js", "$base/src-min/ace.js"],
        "$base/src-min/ace.js",
      ],
      'order in the list does not matter' => [
        ["$base/src-min-noconflict/ace.js", "$base/src/ace.js"],
        "$base/src-min-noconflict/ace.js",
      ],
      'an unknown layout is used as-is' => [
        ['/var/www/html/libraries/ace/ace.js'],
        '/var/www/html/libraries/ace/ace.js',
      ],
      'a known build beats an unknown layout' => [
        ['/var/www/html/libraries/ace/ace.js', "$base/src-noconflict/ace.js"],
        "$base/src-noconflict/ace.js",
      ],
    ];
  }

  /**
   * @covers ::preferred
   */
  #[DataProvider('candidates')]
  public function testPreferred(array $uris, string $expected): void {
    $this->assertSame($expected, AceEditorLibraryPreference::preferred($uris));
  }

  /**
   * An empty candidate list returns NULL rather than a bogus path.
   *
   * @covers ::preferred
   */
  public function testEmptyCandidateList(): void {
    $this->assertNull(AceEditorLibraryPreference::preferred([]));
  }

}

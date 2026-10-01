<?php

namespace Drupal\Tests\xinshi_editor\Unit;

use Drupal\editor\Entity\Editor;
use Drupal\xinshi_editor\Plugin\Editor\JsonEditor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercises JS settings for new, legacy and form-saved editor configuration. */
final class JsonEditorSettingsTest extends TestCase {

  public static function settings(): array {
    return [
      'new format' => [[], ['height' => '600px', 'mode' => 'code', 'allow_modes' => []]],
      'legacy defaults' => [
        ['height' => '600px', 'mode' => 'mode', 'allow_modes' => []],
        ['height' => '600px', 'mode' => 'code', 'allow_modes' => []],
      ],
      'legacy flat' => [
        ['height' => '75vh', 'mode' => 'tree', 'allow_modes' => ['tree' => 'tree', 'view' => '0']],
        ['height' => '75vh', 'mode' => 'tree', 'allow_modes' => ['tree']],
      ],
      'explicit null in flat settings' => [
        ['height' => '75vh', 'mode' => 'tree', 'allow_modes' => NULL],
        ['height' => '75vh', 'mode' => 'tree', 'allow_modes' => []],
      ],
      'explicit null in form settings' => [
        ['fieldset' => ['height' => '80vh', 'mode' => 'text', 'allow_modes' => NULL]],
        ['height' => '80vh', 'mode' => 'text', 'allow_modes' => []],
      ],
      'form fieldset wins over defaults' => [
        ['height' => '600px', 'mode' => 'code', 'allow_modes' => [], 'fieldset' => [
          'height' => '80vh', 'mode' => 'text', 'allow_modes' => ['code' => 0, 'text' => 'text'],
        ]],
        ['height' => '80vh', 'mode' => 'text', 'allow_modes' => ['text']],
      ],
    ];
  }

  #[DataProvider('settings')]
  public function testJsSettingsPreserveChoicesWithoutMutatingEntity(array $stored, array $expected): void {
    $editor = $this->createMock(Editor::class);
    $editor->method('getSettings')->willReturn($stored);
    $editor->expects($this->never())->method('setSettings');
    $editor->expects($this->never())->method('save');
    $plugin = new JsonEditor([], 'json_editor', []);
    $this->assertSame($expected, $plugin->getJsSettings($editor));
  }

  public function testNewDefaultUsesASupportedMode(): void {
    $plugin = new JsonEditor([], 'json_editor', []);
    $this->assertContains($plugin->getDefaultSettings()['mode'], ['code', 'form', 'text', 'tree', 'view', 'preview']);
  }

  public function testSettingsFormCanReopenNullAllowedModes(): void {
    require_once dirname(__DIR__, 7) . '/docroot/core/includes/bootstrap.inc';
    $plugin = new JsonEditor([], 'json_editor', []);
    $form = $plugin->getForm(['height' => '75vh', 'mode' => 'tree', 'allow_modes' => NULL]);
    $this->assertSame([], $form['allow_modes']['#default_value']);
    $this->assertSame('tree', $form['mode']['#default_value']);
    $this->assertSame('75vh', $form['height']['#default_value']);
  }

}

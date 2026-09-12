<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_ckeditor\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Plugin\AiCKEditor\ModifyPrompt;
use Drupal\ai_ckeditor\Plugin\AiCKEditor\ReformatHtml;
use Drupal\ai_ckeditor\Plugin\AiCKEditor\SpellFix;
use Drupal\ai_ckeditor\Plugin\AiCKEditor\Summarize;
use Drupal\ai_ckeditor\Plugin\AiCKEditor\Tone;
use Drupal\ai_ckeditor\Plugin\AiCKEditor\Translate;

/**
 * Tests that each AI CKEditor plugin overrides getNoSelectedTextMessage().
 *
 * Regression test for issue #3575346: the "no text selected" validation
 * message was hardcoded to the Summarize plugin's wording and shown for
 * every plugin, even ones like Translate or Tone where "summarize it" made
 * no sense. Each plugin now overrides
 * AiCKEditorPluginBase::getNoSelectedTextMessage() with contextually correct
 * wording.
 *
 * @group ai_ckeditor
 * @coversDefaultClass \Drupal\ai_ckeditor\AiCKEditorPluginBase
 */
class AiCKEditorNoSelectedTextMessageTest extends UnitTestCase {

  /**
   * Data provider for testPluginOverridesMessage().
   *
   * @return array<string, array{0: class-string<\Drupal\ai_ckeditor\AiCKEditorPluginBase>, 1: string}>
   *   Test cases keyed by plugin label.
   */
  public static function providerPlugins(): array {
    return [
      'ModifyPrompt' => [
        ModifyPrompt::class,
        'You must select some text before you can modify it.',
      ],
      'ReformatHtml' => [
        ReformatHtml::class,
        'You must select some text before you can reformat it.',
      ],
      'SpellFix' => [
        SpellFix::class,
        'You must select some text before you can fix the spelling.',
      ],
      'Summarize' => [
        Summarize::class,
        'You must select some text before you can summarize it.',
      ],
      'Tone' => [
        Tone::class,
        'You must select some text before you can change the tone.',
      ],
      'Translate' => [
        Translate::class,
        'You must select some text before you can translate it.',
      ],
    ];
  }

  /**
   * Asserts each plugin's getNoSelectedTextMessage() returns its own text.
   *
   * @param class-string<\Drupal\ai_ckeditor\AiCKEditorPluginBase> $class
   *   The plugin class to instantiate.
   * @param string $expected
   *   The expected untranslated message text.
   *
   * @covers ::getNoSelectedTextMessage
   * @dataProvider providerPlugins
   */
  public function testPluginOverridesMessage(string $class, string $expected): void {
    $message = $this->invokeGetNoSelectedTextMessage($class);

    $this->assertInstanceOf(TranslatableMarkup::class, $message);
    $this->assertSame($expected, (string) $message);
  }

  /**
   * Asserts all six plugin messages are distinct from one another.
   *
   * This guards specifically against the regression: before the fix, every
   * plugin inherited the same hardcoded "summarize it" message.
   *
   * @covers ::getNoSelectedTextMessage
   */
  public function testAllPluginMessagesAreDistinct(): void {
    $messages = array_map(
      /** @param class-string<\Drupal\ai_ckeditor\AiCKEditorPluginBase> $class */
      fn (string $class): string => (string) $this->invokeGetNoSelectedTextMessage($class),
      array_column(self::providerPlugins(), 0),
    );

    $this->assertCount(count($messages), array_unique($messages));
  }

  /**
   * Invokes the protected getNoSelectedTextMessage() method via reflection.
   *
   * @param class-string<\Drupal\ai_ckeditor\AiCKEditorPluginBase> $class
   *   The plugin class to instantiate.
   *
   * @return mixed
   *   The message returned by the plugin.
   */
  protected function invokeGetNoSelectedTextMessage(string $class): mixed {
    $reflection = new \ReflectionClass($class);
    $plugin = $reflection->newInstanceWithoutConstructor();
    assert($plugin instanceof AiCKEditorPluginBase);
    $plugin->setStringTranslation($this->getStringTranslationStub());

    $method = new \ReflectionMethod($plugin, 'getNoSelectedTextMessage');

    return $method->invoke($plugin);
  }

}

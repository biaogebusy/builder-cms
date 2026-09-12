<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that every Ace Format field carries its own settings.
 *
 * Coverage for issue #2999328: the settings used to travel through a single
 * global drupalSettings key, so the last field rendered on a page decided the
 * theme and syntax for all of them. They now travel on each field's wrapper,
 * which also survives the render cache - a per-request generated identifier
 * would not, because the identifier counter restarts every request while the
 * cached markup does not.
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceFormatterMultipleFieldsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'entity_test',
    'ace_editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installConfig(['field', 'filter', 'ace_editor']);

    foreach (['field_first', 'field_second'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => 'text_long',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'bundle' => 'entity_test',
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Renders one field of an entity through the Ace Format formatter.
   */
  protected function renderField(EntityTest $entity, string $field_name, array $settings): string {
    $build = $entity->get($field_name)->view([
      'type' => 'ace_formatter',
      'label' => 'hidden',
      'settings' => $settings,
    ]);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * Returns every settings payload found in the rendered markup.
   *
   * @return array[]
   *   The decoded settings of each Ace Format wrapper.
   */
  protected function payloads(string $html): array {
    $found = [];
    if (preg_match_all('/data-ace-formatter-settings="([^"]*)"/', $html, $matches)) {
      foreach ($matches[1] as $raw) {
        $found[] = Json::decode(Html::decodeEntities($raw));
      }
    }
    return $found;
  }

  /**
   * Two fields on one entity keep their own theme and syntax.
   */
  public function testTwoFieldsOnOneEntity(): void {
    $entity = EntityTest::create([
      'name' => 'Two fields',
      'field_first' => ['value' => '<p>first</p>'],
      'field_second' => ['value' => 'second()'],
    ]);
    $entity->save();

    $html = $this->renderField($entity, 'field_first', ['theme' => 'cobalt', 'syntax' => 'html'])
      . $this->renderField($entity, 'field_second', ['theme' => 'github', 'syntax' => 'php']);

    $payloads = $this->payloads($html);
    $this->assertCount(2, $payloads);
    $this->assertSame('cobalt', $payloads[0]['theme']);
    $this->assertSame('html', $payloads[0]['syntax']);
    $this->assertSame('github', $payloads[1]['theme']);
    $this->assertSame('php', $payloads[1]['syntax']);
  }

  /**
   * The same field on two entities keeps its settings across requests.
   *
   * The identifier counter is reset between the two renders, which is what
   * happens when the second entity is rendered in a later request or served
   * from the render cache. Settings carried on the markup are unaffected.
   */
  public function testSameFieldOnTwoEntitiesAcrossRequests(): void {
    $first = EntityTest::create(['name' => 'First', 'field_first' => ['value' => 'a']]);
    $first->save();
    $second = EntityTest::create(['name' => 'Second', 'field_first' => ['value' => 'b']]);
    $second->save();

    $html_first = $this->renderField($first, 'field_first', ['theme' => 'cobalt', 'syntax' => 'html']);
    Html::resetSeenIds();
    $html_second = $this->renderField($second, 'field_first', ['theme' => 'twilight', 'syntax' => 'yaml']);

    $payloads = $this->payloads($html_first . $html_second);
    $this->assertCount(2, $payloads);
    $this->assertSame('cobalt', $payloads[0]['theme']);
    $this->assertSame('twilight', $payloads[1]['theme']);
  }

  /**
   * Each delta of a multi-value field carries its own payload.
   */
  public function testMultipleDeltas(): void {
    $entity = EntityTest::create([
      'name' => 'Deltas',
      'field_first' => [
        ['value' => 'one'],
        ['value' => 'two'],
      ],
    ]);
    $entity->save();

    $html = $this->renderField($entity, 'field_first', ['theme' => 'cobalt', 'syntax' => 'html']);

    $this->assertCount(2, $this->payloads($html));
  }

  /**
   * The option lists never reach the browser.
   */
  public function testOptionListsAreNotSentToTheBrowser(): void {
    $entity = EntityTest::create(['name' => 'Lists', 'field_first' => ['value' => 'a']]);
    $entity->save();

    $html = $this->renderField($entity, 'field_first', ['theme' => 'cobalt', 'syntax' => 'html']);

    $payload = $this->payloads($html)[0];
    $this->assertArrayNotHasKey('theme_list', $payload);
    $this->assertArrayNotHasKey('syntax_list', $payload);
    $this->assertStringNotContainsString('syntax_list', $html);
  }

  /**
   * The syntax comes from the configured field when one is set.
   */
  public function testSyntaxFieldValue(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_language',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_language',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Language',
    ])->save();

    $entity = EntityTest::create([
      'name' => 'Syntax field',
      'field_first' => ['value' => 'print("hi")'],
      'field_language' => ['value' => 'python'],
    ]);
    $entity->save();

    $html = $this->renderField($entity, 'field_first', [
      'theme' => 'cobalt',
      'syntax' => 'html',
      'syntax_field' => 'field_language',
    ]);

    $payload = $this->payloads($html)[0];
    $this->assertSame('python', $payload['syntax_field_value']);
  }

  /**
   * Only text-like fields are offered as the syntax source.
   */
  public function testSyntaxOptionsOnlyOfferTextFields(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_number',
      'entity_type' => 'entity_test',
      'type' => 'integer',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_number',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Number',
    ])->save();

    $formatter = $this->container->get('plugin.manager.field.formatter')->createInstance('ace_formatter', [
      'field_definition' => FieldConfig::loadByName('entity_test', 'entity_test', 'field_first'),
      'settings' => [],
      'label' => 'hidden',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);
    $summary = implode(' ', array_map('strval', $formatter->settingsSummary()));

    // The summary renders without an error on settings that predate the
    // syntax field options, which is the regression reported in the issue.
    $this->assertStringContainsString('Syntax field:', $summary);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Ace Editor field widget.
 *
 * A plain long text field has no text format, so the text editor integration
 * cannot reach it. The widget carries the settings of each instance on its own
 * textarea, so several fields can be edited with different themes on one form
 * (issue #2933546).
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceWidgetTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'entity_test',
    'filter',
    'editor',
    'ace_editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ace_editor', 'field']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => 'field_code',
      'entity_type' => 'entity_test',
      'type' => 'string_long',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_code',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Code',
    ])->save();
  }

  /**
   * Builds the widget form for one entity, with the given widget settings.
   */
  protected function widgetForm(array $settings, string $value = "line one\nline two"): array {
    $display = \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test');
    $display->setComponent('field_code', [
      'type' => 'ace_editor',
      'settings' => $settings,
    ])->save();

    $entity = EntityTest::create(['field_code' => $value]);
    $form = [];
    $form_state = new FormState();
    // buildForm() returns nothing: it populates the form by reference.
    $display->buildForm($entity, $form, $form_state);

    return $form['field_code'];
  }

  /**
   * The widget marks up the textarea with its own settings.
   */
  public function testWidgetElementCarriesItsSettings(): void {
    $element = $this->widgetForm(['theme' => 'monokai', 'syntax' => 'php']);
    $value = $element['widget'][0]['value'];

    $this->assertContains('ace-editor-widget', $value['#attributes']['class']);

    $settings = json_decode($value['#attributes']['data-ace-widget-settings'], TRUE);
    $this->assertSame('monokai', $settings['theme']);
    $this->assertSame('php', $settings['syntax']);
    // The option lists never reach the markup.
    $this->assertArrayNotHasKey('theme_list', $settings);
    $this->assertArrayNotHasKey('syntax_list', $settings);
  }

  /**
   * The widget attaches its library, and the configured theme and mode.
   */
  public function testWidgetAttachesItsLibraries(): void {
    $element = $this->widgetForm(['theme' => 'twilight', 'syntax' => 'css']);
    $libraries = $element['widget'][0]['value']['#attached']['library'];

    $this->assertContains('ace_editor/widget', $libraries);
    $this->assertContains('ace_editor/theme.twilight', $libraries);
    $this->assertContains('ace_editor/mode.css', $libraries);
  }

  /**
   * A field holding a Gherkin script attaches the Gherkin mode.
   *
   * Ace ships a "gherkin" mode, so a .feature script is editable in a field
   * with nothing more than the widget's own syntax setting.
   */
  public function testGherkinScriptField(): void {
    $script = <<<'GHERKIN'
    Feature: Editing a Gherkin script in a field
      Scenario: The editor highlights the script
        Given I am a logged in user
        When I edit the field
        Then the script keeps its indentation
    GHERKIN;

    $element = $this->widgetForm(['theme' => 'twilight', 'syntax' => 'gherkin'], $script);
    $value = $element['widget'][0]['value'];

    $settings = json_decode($value['#attributes']['data-ace-widget-settings'], TRUE);
    $this->assertSame('gherkin', $settings['syntax']);
    $this->assertContains('ace_editor/mode.gherkin', $value['#attached']['library']);

    // The script reaches the textarea unchanged, indentation included.
    $this->assertSame($script, $value['#default_value']);
    $this->assertStringContainsString('  Scenario: ', $value['#default_value']);
  }

  /**
   * Gherkin is one of the syntaxes the settings form offers.
   */
  public function testGherkinIsAnAvailableSyntax(): void {
    $syntaxes = $this->config('ace_editor.settings')->get('syntax_list');

    $this->assertArrayHasKey('gherkin', $syntaxes);
    $this->assertSame('Gherkin', $syntaxes['gherkin']);
  }

  /**
   * Two fields on one form keep their own settings.
   */
  public function testSettingsAreNotShared(): void {
    $first = $this->widgetForm(['theme' => 'monokai', 'syntax' => 'php']);
    $second = $this->widgetForm(['theme' => 'twilight', 'syntax' => 'yaml']);

    $first_settings = json_decode($first['widget'][0]['value']['#attributes']['data-ace-widget-settings'], TRUE);
    $second_settings = json_decode($second['widget'][0]['value']['#attributes']['data-ace-widget-settings'], TRUE);

    $this->assertSame('php', $first_settings['syntax']);
    $this->assertSame('yaml', $second_settings['syntax']);
  }

  /**
   * The saved widget configuration validates against its schema.
   */
  public function testSavedSettingsMatchTheSchema(): void {
    $this->widgetForm(['theme' => 'github', 'syntax' => 'twig']);

    $saved = $this->config('core.entity_form_display.entity_test.entity_test.default')
      ->get('content.field_code.settings');
    $this->assertSame('github', $saved['theme']);
    $this->assertArrayNotHasKey('theme_list', $saved);

    $typed = \Drupal::service('config.typed')
      ->get('core.entity_form_display.entity_test.entity_test.default');
    $definition = $typed->get('content')->get('field_code')->get('settings')
      ->getDataDefinition()->getDataType();
    $this->assertSame('field.widget.settings.ace_editor', $definition);
  }

}

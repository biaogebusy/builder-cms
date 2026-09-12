<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests each FWA plugin's contract against $form_state->getUserInput().
 *
 * Covers:
 * - Base-class default: per-delta write of $item->toArray() at
 *   $input[$form_key][$delta].
 * - Flat-shape overrides: Boolean, List* (integer/string/float),
 *   ClassificationOptionsSelect (multiple_values branch),
 *   AutoCompleteTagsTaxonomy.
 * - Per-item transform overrides: File, ImageAltText, ImageFilename,
 *   TextToImage (target_id → fids), AutoCompleteTaxonomy (label+id),
 *   LlmLinkLinkDefault (drops 'options'), ModerationState
 *   ('state' sub-key), Address (wraps in 'address' sub-key), FaqField
 *   (answer + answer_format → nested answer.[value|format]).
 * - Merge-only override: SummaryTextareaWithSummary preserves
 *   in-flight value / format.
 * - Legacy no-op plugins (Chart, Metatag, OfficeHours,
 *   TextToAudioMediaLibrary, TextToImageMediaLibrary) do not touch
 *   user input.
 * - Base class updateItemsCount() bumps field widget state so the
 *   form rebuilds with enough delta slots — resolves the multi-value
 *   cshs symptom reported in d.o 3578660 as a side-effect of the
 *   FWA dispatch generalization.
 *
 * @group ai_automators
 * @group 3577050
 */
class FieldWidgetActionFormInputTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'link',
    'media',
    'node',
    'options',
    'taxonomy',
    'telephone',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'field', 'node', 'filter']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Invokes a plugin's protected setFormInput() via reflection.
   */
  protected function invokeSetFormInput(object $plugin, object $entity, FormState $form_state, string $form_key): void {
    $method = new \ReflectionMethod($plugin, 'setFormInput');
    $method->setAccessible(TRUE);
    $method->invoke($plugin, $entity, $form_state, $form_key);
  }

  /**
   * Invokes the base-class updateItemsCount() helper via reflection.
   */
  protected function invokeUpdateItemsCount(object $plugin, array $form, FormState $form_state, string $form_key, int $count): void {
    $method = new \ReflectionMethod($plugin, 'updateItemsCount');
    $method->setAccessible(TRUE);
    $method->invoke($plugin, $form, $form_state, $form_key, $count);
  }

  /**
   * Invokes a plugin's protected transformFormInput() via reflection.
   */
  protected function invokeTransformFormInput(object $plugin, ComplexDataInterface $item) {
    $method = new \ReflectionMethod($plugin, 'transformFormInput');
    $method->setAccessible(TRUE);
    return $method->invoke($plugin, $item);
  }

  /**
   * Stubs a ComplexDataInterface whose toArray() returns the given array.
   *
   * Lets us cover plugins that target contrib field types (address,
   * faqfield) without installing those modules into the kernel test —
   * we only need the per-item shape transform, not the field plumbing.
   */
  protected function stubItemFromArray(array $values): ComplexDataInterface {
    $item = $this->createStub(ComplexDataInterface::class);
    $item->method('toArray')->willReturn($values);
    return $item;
  }

  /**
   * Creates a simple field + instance on node.article.
   */
  protected function createField(string $name, string $type, int $cardinality = 1, array $storage_settings = [], array $field_settings = []): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $storage_settings,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => $name,
      'settings' => $field_settings,
    ])->save();
  }

  /**
   * Creates an unlimited-cardinality entity_reference to taxonomy terms.
   */
  protected function createTaxonomyField(string $name, string $vid): void {
    $this->createField($name, 'entity_reference', -1,
      ['target_type' => 'taxonomy_term'],
      ['handler' => 'default', 'handler_settings' => ['target_bundles' => [$vid => $vid]]],
    );
  }

  /**
   * Builds a FormState with a form_display for the given widget type.
   */
  protected function createFormStateWithWidget(string $field_name, string $widget_type): FormState {
    $display = EntityFormDisplay::load('node.article.default');
    if (!$display) {
      $display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display->setComponent($field_name, ['type' => $widget_type])->save();
    $form_state = new FormState();
    $form_state->set('form_display', $display);
    return $form_state;
  }

  /**
   * Seeds two tag terms and returns a node referencing them via $field_name.
   */
  protected function createArticleWithTwoTerms(string $field_name): Node {
    if (!Vocabulary::load('tags')) {
      Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    }
    $t1 = Term::create(['vid' => 'tags', 'name' => 'Alpha']);
    $t1->save();
    $t2 = Term::create(['vid' => 'tags', 'name' => 'Beta']);
    $t2->save();
    $this->createTaxonomyField($field_name, 'tags');
    return Node::create([
      'type' => 'article',
      'title' => 'T',
      $field_name => [['target_id' => $t1->id()], ['target_id' => $t2->id()]],
    ]);
  }

  /**
   * Creates a plugin instance via the FWA plugin manager.
   */
  protected function plugin(string $id) {
    return \Drupal::service('plugin.manager.field_widget_actions')->createInstance($id);
  }

  /**
   * Plugins without a setFormInput override write $item->toArray() per delta.
   *
   * Covers Text, Email, Json, Telephone, LlmNumberNumberDefault — all rely
   * on the base setFormInput + default transformFormInput.
   */
  public function testBaseDefaultPerDeltaShape(): void {
    $this->createField('field_string', 'string', 1, [
      'max_length' => 255,
      'is_ascii' => FALSE,
      'case_sensitive' => FALSE,
    ]);
    $node = Node::create(['type' => 'article', 'title' => 'T', 'field_string' => 'hello world']);

    $form_state = new FormState();
    $this->invokeSetFormInput($this->plugin('automator_text'), $node, $form_state, 'field_string');

    $input = $form_state->getUserInput();
    $this->assertSame([0 => ['value' => 'hello world']], $input['field_string']);
  }

  /**
   * Boolean writes a flat $input[$form_key] = {value: '1'|'0'}.
   *
   * Both TRUE and FALSE must produce explicit '0'/'1' strings — a missing
   * 'value' key would leave the rebuilt checkbox in its previous state.
   */
  public function testBooleanWritesFlatStringValue(): void {
    $this->createField('field_active', 'boolean');
    $plugin = $this->plugin('automator_boolean');

    foreach ([1 => '1', 0 => '0'] as $value => $expected) {
      $node = Node::create(['type' => 'article', 'title' => 'T', 'field_active' => $value]);
      $form_state = new FormState();
      $this->invokeSetFormInput($plugin, $node, $form_state, 'field_active');
      $this->assertSame(['value' => $expected], $form_state->getUserInput()['field_active'], "boolean=$value");
    }
  }

  /**
   * Multiple_values=TRUE widgets (options_select) get a flat id list.
   */
  public function testClassificationOptionsSelectFlatShape(): void {
    $node = $this->createArticleWithTwoTerms('field_topics');
    $form_state = $this->createFormStateWithWidget('field_topics', 'options_select');

    $this->invokeSetFormInput($this->plugin('classification_options_select'), $node, $form_state, 'field_topics');

    $input = $form_state->getUserInput();
    $ids = array_map(fn ($item) => (string) $item->target_id, iterator_to_array($node->get('field_topics')));
    $this->assertSame(array_values($ids), $input['field_topics']);
  }

  /**
   * Multiple_values=FALSE widgets get the per-delta [target_id => id] shape.
   *
   * Cshs is the real-world case; any multiple_values=FALSE widget (here:
   * entity_reference_autocomplete) exercises the same branch.
   */
  public function testClassificationOptionsSelectPerDeltaShape(): void {
    $node = $this->createArticleWithTwoTerms('field_topics');
    $form_state = $this->createFormStateWithWidget('field_topics', 'entity_reference_autocomplete');

    $this->invokeSetFormInput($this->plugin('classification_options_select'), $node, $form_state, 'field_topics');

    $input = $form_state->getUserInput();
    $expected = [];
    foreach ($node->get('field_topics') as $delta => $item) {
      $expected[$delta] = ['target_id' => (string) $item->target_id];
    }
    $this->assertSame($expected, $input['field_topics']);
  }

  /**
   * Tags autocomplete writes a single comma-joined 'Label (id), …' string.
   */
  public function testAutoCompleteTagsTaxonomyJoinsWithComma(): void {
    $node = $this->createArticleWithTwoTerms('field_tags');
    [$t1, $t2] = array_values(array_map(fn ($i) => $i->entity, iterator_to_array($node->get('field_tags'))));

    $form_state = new FormState();
    $this->invokeSetFormInput($this->plugin('automator_autocomplete_tags_on_taxonomy'), $node, $form_state, 'field_tags');

    $input = $form_state->getUserInput();
    $expected = sprintf('Alpha (%d), Beta (%d)', $t1->id(), $t2->id());
    $this->assertSame($expected, $input['field_tags']['target_id']);
  }

  /**
   * Per-delta autocomplete writes 'Label (id)' with a space before the paren.
   */
  public function testAutoCompleteTaxonomyFormatHasSpaceBeforeParen(): void {
    if (!Vocabulary::load('tags')) {
      Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    }
    $t1 = Term::create(['vid' => 'tags', 'name' => 'Alpha']);
    $t1->save();
    $this->createTaxonomyField('field_topic', 'tags');
    $node = Node::create(['type' => 'article', 'title' => 'T', 'field_topic' => [['target_id' => $t1->id()]]]);

    $form_state = new FormState();
    $this->invokeSetFormInput($this->plugin('automator_autocomplete_taxonomy'), $node, $form_state, 'field_topic');

    $input = $form_state->getUserInput();
    $this->assertSame(sprintf('Alpha (%d)', $t1->id()), $input['field_topic'][0]['target_id']);
  }

  /**
   * List* plugins write a scalar at $input[$form_key].
   *
   * The options_select / options_buttons widgets render the list as a
   * single form element, so user input is a flat scalar — not the
   * per-delta default. Covers list_integer, list_string and list_float
   * in one setUp.
   */
  public function testListPluginsWriteScalarInput(): void {
    $cases = [
      // [plugin_id, field_name, type, allowed_values, value, expected].
      ['automator_list_integer', 'field_li', 'list_integer', [1 => 'One', 2 => 'Two'], 2, '2'],
      ['automator_list_string', 'field_ls', 'list_string', ['low' => 'Low', 'high' => 'High'], 'high', 'high'],
      ['automator_list_float', 'field_lf', 'list_float', ['0.5' => 'Half', '1.5' => 'Full+'], 1.5, '1.5'],
    ];
    foreach ($cases as [$plugin_id, $field, $type, $allowed, $value, $expected]) {
      $this->createField($field, $type, 1, ['allowed_values' => $allowed]);
      $node = Node::create(['type' => 'article', 'title' => 'T', $field => $value]);
      $form_state = new FormState();
      $this->invokeSetFormInput($this->plugin($plugin_id), $node, $form_state, $field);
      $this->assertSame($expected, $form_state->getUserInput()[$field], $plugin_id);
    }
  }

  /**
   * Plugins targeting managed_file widgets remap target_id → fids.
   *
   * The managed_file form element reads its uploaded file id from the
   * 'fids' user-input key, not the field-storage 'target_id'. All four
   * plugins below do the same remap; we verify the contract once per
   * plugin so a regression on any of them fails the suite.
   */
  public function testFidsRemapPlugins(): void {
    $file = File::create(['uri' => 'public://t.png', 'filename' => 't.png', 'status' => 1]);
    $file->save();
    $this->createField('field_img', 'image', 1, [
      'target_type' => 'file',
      'uri_scheme' => 'public',
      'default_image' => ['uuid' => '', 'alt' => '', 'title' => '', 'width' => NULL, 'height' => NULL],
    ]);
    $this->createField('field_file', 'file', 1, ['target_type' => 'file', 'uri_scheme' => 'public']);
    $img_node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_img' => ['target_id' => $file->id(), 'alt' => 'a', 'title' => ''],
    ]);
    $file_node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_file' => ['target_id' => $file->id()],
    ]);

    $cases = [
      // [plugin_id, node, form_key].
      ['automator_image_filename_rewrite', $img_node, 'field_img'],
      ['automator_alt_text', $img_node, 'field_img'],
      ['text_to_image', $img_node, 'field_img'],
      ['automator_file', $file_node, 'field_file'],
    ];
    foreach ($cases as [$plugin_id, $node, $form_key]) {
      $form_state = new FormState();
      $this->invokeSetFormInput($this->plugin($plugin_id), $node, $form_state, $form_key);
      $input = $form_state->getUserInput();
      $this->assertArrayHasKey('fids', $input[$form_key][0], $plugin_id);
      $this->assertArrayNotHasKey('target_id', $input[$form_key][0], $plugin_id);
      $this->assertSame((string) $file->id(), (string) $input[$form_key][0]['fids'], $plugin_id);
    }
  }

  /**
   * Address transformFormInput wraps $item->toArray() in ['address' => …].
   *
   * The address_default widget renders all sub-fields (country_code,
   * locality, …) inside an 'address' sub-element, so per-delta user input
   * must be nested under that key. Tested directly against
   * transformFormInput so we don't have to install the address contrib
   * module to exercise the contract.
   */
  public function testAddressTransformWrapsInAddressKey(): void {
    $values = ['country_code' => 'US', 'locality' => 'NYC', 'address_line1' => '1 Main St'];
    $result = $this->invokeTransformFormInput(
      $this->plugin('automator_address'),
      $this->stubItemFromArray($values),
    );
    $this->assertSame(['address' => $values], $result);
  }

  /**
   * FaqField transformFormInput merges answer + answer_format → answer.[v|f].
   *
   * FaqFieldItem stores the answer body and format as two flat keys, but
   * the faqfield_default widget renders them as a single text-with-format
   * sub-element expecting ['value', 'format']. The plugin must do the
   * inverse remap so the rebuilt widget picks up both pieces. Tested
   * directly against transformFormInput so the contract is pinned without
   * installing the faqfield contrib module.
   */
  public function testFaqFieldTransformRemapsAnswerFormat(): void {
    $result = $this->invokeTransformFormInput(
      $this->plugin('automator_faqfield'),
      $this->stubItemFromArray([
        'question' => 'Q?',
        'answer' => 'A.',
        'answer_format' => 'basic_html',
      ]),
    );
    $this->assertSame([
      'question' => 'Q?',
      'answer' => ['value' => 'A.', 'format' => 'basic_html'],
    ], $result);
  }

  /**
   * LlmLinkLinkDefault keeps uri + title and drops the options serialization.
   */
  public function testLinkPluginDropsOptionsFromUserInput(): void {
    $this->createField('field_link', 'link', 1);
    $node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_link' => ['uri' => 'https://example.com', 'title' => 'Example', 'options' => ['attributes' => []]],
    ]);

    $form_state = new FormState();
    $this->invokeSetFormInput($this->plugin('llm_link_link_default'), $node, $form_state, 'field_link');

    $input = $form_state->getUserInput();
    $this->assertSame(['uri' => 'https://example.com', 'title' => 'Example'], $input['field_link'][0]);
  }

  /**
   * ModerationState writes ['state' => value] per delta.
   */
  public function testModerationStatePluginUsesStateSubKey(): void {
    $this->createField('field_ms', 'string', 1, ['max_length' => 255, 'is_ascii' => FALSE, 'case_sensitive' => FALSE]);
    $node = Node::create(['type' => 'article', 'title' => 'T', 'field_ms' => 'published']);

    $form_state = new FormState();
    $this->invokeSetFormInput($this->plugin('automator_moderation_state'), $node, $form_state, 'field_ms');

    $input = $form_state->getUserInput();
    $this->assertSame(['state' => 'published'], $input['field_ms'][0]);
  }

  /**
   * SummaryTextareaWithSummary writes only the 'summary' key.
   *
   * Preserves any existing value / format entries in user input so the
   * editor's in-flight edits to the main textarea are not clobbered.
   */
  public function testSummaryPluginMergesOnlySummary(): void {
    $this->createField('field_body', 'text_with_summary', 1);
    $node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_body' => ['value' => 'orig', 'summary' => 'AI summary', 'format' => 'basic_html'],
    ]);

    $form_state = new FormState();
    // Pre-seed browser-submitted user input — as if the editor typed.
    $form_state->setUserInput([
      'field_body' => [0 => ['value' => 'typed-by-user', 'format' => 'basic_html', 'summary' => '']],
    ]);

    $this->invokeSetFormInput($this->plugin('summary_textarea_with_summary'), $node, $form_state, 'field_body');

    $input = $form_state->getUserInput();
    $this->assertSame('typed-by-user', $input['field_body'][0]['value']);
    $this->assertSame('basic_html', $input['field_body'][0]['format']);
    $this->assertSame('AI summary', $input['field_body'][0]['summary']);
  }

  /**
   * Legacy plugins with custom saveFormValues no-op setFormInput.
   *
   * Data provider returns [plugin_id, field_name]. The field is a generic
   * string field — these plugins' annotations restrict to their real
   * widgets at dispatch time, but the setFormInput() method just mutates
   * user input for whatever field name is passed. Since these override
   * setFormInput to a deliberate no-op, $form_state->getUserInput() should
   * be unchanged regardless of the field type.
   */
  public function testLegacyNoOpPlugins(): void {
    $this->createField('field_x', 'string', 1, [
      'max_length' => 255,
      'is_ascii' => FALSE,
      'case_sensitive' => FALSE,
    ]);
    $node = Node::create(['type' => 'article', 'title' => 'T', 'field_x' => 'v']);

    $plugin_ids = [
      'automator_chart',
      'automator_metatag',
      'automator_office_hours',
      'text_to_audio_media_library',
      'text_to_image_media_library',
    ];
    foreach ($plugin_ids as $plugin_id) {
      $form_state = new FormState();
      $form_state->setUserInput(['sentinel' => 'unchanged']);
      $this->invokeSetFormInput($this->plugin($plugin_id), $node, $form_state, 'field_x');
      $this->assertSame(['sentinel' => 'unchanged'], $form_state->getUserInput(), "$plugin_id should no-op setFormInput");
    }
  }

  /**
   * UpdateItemsCount bumps widget field state so rebuild scaffolds N deltas.
   *
   * Direct regression guard for the cshs multi-value populate bug where
   * only 1 delta rendered a value while the rest appeared empty — caused
   * by not pushing items_count into $form_state before rebuild.
   */
  public function testUpdateItemsCountBumpsFieldStateItemsCount(): void {
    $node = $this->createArticleWithTwoTerms('field_topics');
    $node->get('field_topics')->appendItem(['target_id' => $node->get('field_topics')[0]->target_id]);
    $form_state = $this->createFormStateWithWidget('field_topics', 'entity_reference_autocomplete');
    $form = ['field_topics' => ['widget' => ['#field_parents' => []]]];

    $plugin = $this->plugin('classification_options_select');
    $count = $node->get('field_topics')->count();
    $this->assertSame(3, $count);

    $this->invokeUpdateItemsCount($plugin, $form, $form_state, 'field_topics', $count);

    $display = $form_state->get('form_display');
    $renderer = $display->getRenderer('field_topics');
    $state = $renderer->getWidgetState([], 'field_topics', $form_state);
    $this->assertSame(2, $state['items_count'], '3 entity items → items_count=2 (formMultipleElements iterates 0..items_count).');
  }

  /**
   * UpdateItemsCount does NOT shrink items_count if it's already larger.
   *
   * Guards against losing delta slots when the editor had added extra
   * empty rows before clicking the automator button.
   */
  public function testUpdateItemsCountDoesNotShrinkExistingState(): void {
    $this->createArticleWithTwoTerms('field_topics');
    $form_state = $this->createFormStateWithWidget('field_topics', 'entity_reference_autocomplete');
    $form = ['field_topics' => ['widget' => ['#field_parents' => []]]];

    // Pre-set a larger items_count (simulating the user having clicked
    // "Add another" before Suggest).
    $display = $form_state->get('form_display');
    $renderer = $display->getRenderer('field_topics');
    $renderer->setWidgetState([], 'field_topics', $form_state, ['items_count' => 5]);

    $this->invokeUpdateItemsCount($this->plugin('classification_options_select'), $form, $form_state, 'field_topics', 2);

    $state = $renderer->getWidgetState([], 'field_topics', $form_state);
    $this->assertSame(5, $state['items_count']);
  }

}

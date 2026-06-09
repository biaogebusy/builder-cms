<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Element;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ai_json_schema form element.
 *
 * @coversDefaultClass \Drupal\ai\Element\AiJsonSchema
 *
 * @group ai
 */
class AiJsonSchemaElementTest extends KernelTestBase implements FormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai'];

  /**
   * The default value used in tests.
   */
  protected string $defaultValue = '{"type": "object", "properties": {}}';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_json_schema_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['json_schema'] = [
      '#type' => 'ai_json_schema',
      '#title' => $this->t('JSON Schema'),
      '#description' => $this->t('Enter a valid JSON schema.'),
      '#default_value' => $this->defaultValue,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Tests that the element builds the correct render structure.
   */
  public function testElementStructure(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    // The element should exist in the form.
    $this->assertArrayHasKey('json_schema', $form);

    $element = $form['json_schema'];
    $children = Element::children($element);

    // Should have three children: value, fallback, editor.
    $this->assertContains('value', $children);
    $this->assertContains('fallback', $children);
    $this->assertContains('editor', $children);

    // The hidden input should be an html_tag with type=hidden.
    $this->assertEquals('html_tag', $element['value']['#type']);
    $this->assertEquals('input', $element['value']['#tag']);
    $this->assertEquals('hidden', $element['value']['#attributes']['type']);
    $this->assertArrayHasKey('data-ai-json-schema-textarea', $element['value']['#attributes']);

    // The fallback textarea should be an html_tag with tag=textarea.
    $this->assertEquals('html_tag', $element['fallback']['#type']);
    $this->assertEquals('textarea', $element['fallback']['#tag']);
    $this->assertArrayHasKey('data-ai-json-schema-fallback', $element['fallback']['#attributes']);

    // The editor wrapper should be an html_tag with display:none.
    $this->assertEquals('html_tag', $element['editor']['#type']);
    $this->assertEquals('div', $element['editor']['#tag']);
    $this->assertContains('ai-json-schema-editor-wrapper', $element['editor']['#attributes']['class']);
    $this->assertEquals('display: none;', $element['editor']['#attributes']['style']);

    // The library should be attached.
    $this->assertContains('ai/json_schema_editor', $element['#attached']['library']);
  }

  /**
   * Tests that paired data attributes link the elements together.
   */
  public function testElementDataAttributes(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $element = $form['json_schema'];

    // All three children should share the same data attribute value.
    $editor_id = $element['value']['#attributes']['data-ai-json-schema-textarea'];
    $this->assertNotEmpty($editor_id);
    $this->assertEquals($editor_id, $element['fallback']['#attributes']['data-ai-json-schema-fallback']);
    $this->assertEquals($editor_id, $element['editor']['#attributes']['data-ai-json-schema-editor']);
  }

  /**
   * Tests that the default value is set correctly.
   */
  public function testDefaultValue(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $element = $form['json_schema'];

    // Hidden input should carry the default value.
    $this->assertEquals($this->defaultValue, $element['value']['#attributes']['value']);

    // Fallback textarea should also contain the (HTML-escaped) default value.
    $this->assertStringContainsString(htmlspecialchars($this->defaultValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $element['fallback']['#value']);
  }

  /**
   * Tests that the element works with an empty default value.
   */
  public function testEmptyDefaultValue(): void {
    $this->defaultValue = '';
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $element = $form['json_schema'];

    // Should still have the correct structure.
    $this->assertEquals('html_tag', $element['value']['#type']);
    $this->assertEquals('html_tag', $element['fallback']['#type']);
    $this->assertEquals('html_tag', $element['editor']['#type']);

    // Value should be empty string.
    $this->assertEquals('', $element['value']['#attributes']['value']);
  }

  /**
   * Tests that form submission returns the correct value.
   */
  public function testFormSubmission(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'json_schema' => '{"type": "string"}',
      'op' => 'Submit',
    ]);
    $form_state->setMethod('POST');
    $form_state->setProgrammed();

    \Drupal::formBuilder()->submitForm($this, $form_state);

    $this->assertEquals('{"type": "string"}', $form_state->getValue('json_schema'));
  }

  /**
   * Tests that the hidden input name is built from form parents.
   */
  public function testInputName(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $element = $form['json_schema'];

    // The hidden input should have a name attribute matching the element key.
    $this->assertEquals('json_schema', $element['value']['#attributes']['name']);
  }

}

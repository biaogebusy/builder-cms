<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\FormElement;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\FormElement\MessageTemplateMultipleTextField;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Kernel tests for the multiple text field form helper.
 *
 * @coversDefaultClass \Drupal\message\FormElement\MessageTemplateMultipleTextField
 *
 * @group Message
 */
class MessageTemplateMultipleTextFieldTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
  }

  /**
   * Tests building fields for an empty template, including token tree.
   *
   * @covers ::__construct
   * @covers ::textField
   * @covers ::singleElement
   */
  public function testTextFieldEmptyWithTokenTree(): void {
    $entity = MessageTemplate::create([
      'template' => 'empty_text',
      'label' => 'Empty',
      'text' => [],
    ]);
    $element = new MessageTemplateMultipleTextField(
      $entity,
      [static::class, 'addMoreAjax'],
      $entity->language()->getId()
    );

    $form = [];
    $form_state = new FormState();
    $element->textField($form, $form_state, TRUE);

    $this->assertArrayHasKey('text', $form);
    $this->assertArrayHasKey('token_tree', $form);
    $this->assertEquals('token_tree_link', $form['token_tree']['#theme']);
    $this->assertArrayHasKey('add_more', $form);
    // Empty templates still get one starter text element.
    $this->assertCount(1, array_filter(array_keys($form['text']), 'is_int'));
    $this->assertEquals('text_format', $form['text'][0]['#type']);
  }

  /**
   * Tests building fields for a template that already has text items.
   *
   * @covers ::textField
   * @covers ::singleElement
   */
  public function testTextFieldWithExistingItemsAndAddMore(): void {
    $entity = $this->createMessageTemplate(
      'existing_text',
      'Existing',
      'Description',
      ['First', 'Second']
    );
    $element = new MessageTemplateMultipleTextField(
      $entity,
      [static::class, 'addMoreAjax'],
      $entity->language()->getId()
    );

    $form = [];
    $form_state = new FormState();
    $form_state->setTriggeringElement([
      '#add_more' => TRUE,
      '#name' => 'add_more',
    ]);
    $element->textField($form, $form_state, FALSE);

    $this->assertArrayNotHasKey('token_tree', $form);
    $integer_keys = array_values(array_filter(array_keys($form['text']), 'is_int'));
    $this->assertCount(3, $integer_keys);
    $this->assertEquals('First', $form['text'][0]['#default_value']);
    $this->assertEquals('Second', $form['text'][1]['#default_value']);
    $this->assertEquals('', $form['text'][2]['#default_value']);
  }

  /**
   * Dummy Ajax callback referenced by the helper.
   */
  public static function addMoreAjax(array $form, FormState $form_state): array {
    return $form['text'];
  }

}

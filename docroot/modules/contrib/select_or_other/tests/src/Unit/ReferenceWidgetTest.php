<?php

namespace Drupal\Tests\select_or_other\Unit;

use Drupal\Core\Form\FormState;
use Drupal\select_or_other\Plugin\Field\FieldWidget\ReferenceWidget;

/**
 * Tests the form element implementation.
 *
 * @group select_or_other
 *
 * @covers Drupal\select_or_other\Plugin\Field\FieldWidget\ReferenceWidget
 */
class ReferenceWidgetTest extends UnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getTestedClassName() {
    return 'Drupal\select_or_other\Plugin\Field\FieldWidget\ReferenceWidget';
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFormElementMock($target_type = 'entity', $tested_class_name = FALSE) {
    $methods = [
      'getColumn',
      'getOptions',
      'getSelectedOptions',
      'getFieldSetting',
    ];

    if ($tested_class_name === FALSE) {
      $methods[] = 'getAutoCreateBundle';
      $methods[] = 'getCreateAccess';
    }

    // Get the mockBuilder.
    if ($tested_class_name) {
      $builder = $this->getMockBuilder($tested_class_name);
    }
    else {
      // We don't use $this->mockBuilder because it gives issues since
      // setMethods is deprecated and onlyMethods just adds methods.
      $builder = $this->getMockBuilder($this->getTestedClassName());
    }

    // Configure the mockBuilder.
    $field_definition = $this->createMock('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_definition->expects($this->any())
      ->method('getFieldStorageDefinition')
      ->willReturn($this->createMock('Drupal\Core\Field\FieldStorageDefinitionInterface'));
    $user = $this->createMock('\Drupal\Core\Session\AccountInterface');
    $user->method('id')->willReturn('1');
    $constructor_arguments = [
      '',
      '',
      $field_definition,
      [],
      [],
      NULL,
      NULL,
      $user,
    ];

    $builder->setConstructorArgs($constructor_arguments)
      ->onlyMethods($methods);

    // Configure the mock.
    $mock = $builder->getMock();
    $mock->method('getColumn')->willReturn('column');
    $mock->method('getOptions')->willReturn([]);
    $mock->method('getSelectedOptions')->willReturn([]);
    $mock->method('getFieldSetting')->willReturnOnConsecutiveCalls($target_type, 'some_handler', [], $target_type);
    if ($tested_class_name === FALSE) {
      $mock->method('getAutoCreateBundle')
        ->willReturn('autoCreateBundle');
    }

    return $mock;
  }

  /**
   * Test if defaultSettings() returns the correct keys.
   */
  public function testGetOptions() {
    $entity_id = 1;
    $entity_label = 'Label';
    $entity_mock = $this->createMock('\Drupal\Core\Entity\EntityBase');
    $entity_mock->expects($this->exactly(1))
      ->method('id')
      ->willReturn($entity_id);
    $entity_mock->expects($this->exactly(2))
      ->method('label')
      ->willReturn($entity_label);

    $entity_storage_mock = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $entity_storage_mock->expects($this->exactly(2))
      ->method('loadByProperties')
      ->willReturnOnConsecutiveCalls([], [$entity_mock]);

    $mock = $this->mockBuilder->disableOriginalConstructor()
      ->onlyMethods([
        'getEntityStorage',
        'getBundleKey',
        'getSelectionHandlerSetting',
      ])
      ->getMock();
    $mock->expects($this->exactly(2))
      ->method('getEntityStorage')
      ->willReturn($entity_storage_mock);
    $mock->expects($this->exactly(2))
      ->method('getBundleKey')
      ->willReturn('bundle');
    $mock->expects($this->exactly(2))
      ->method('getSelectionHandlerSetting')
      ->willReturn('target_bundle');

    $get_options = new \ReflectionMethod($mock, 'getOptions');
    $get_options->setAccessible(TRUE);

    // First invocation returns an empty array because there are no entities.
    $options = $get_options->invoke($mock, $this->createMock('Drupal\Core\Entity\FieldableEntityInterface'));
    $expected = [];
    $this->assertEquals($options, $expected);

    // Second invocation returns a key=>value array because there is one entity.
    $options = $get_options->invoke($mock, $this->createMock('Drupal\Core\Entity\FieldableEntityInterface'));
    $expected = ["{$entity_label} ({$entity_id})" => $entity_label];
    $this->assertEquals($options, $expected);
  }

  /**
   * Test if formElement() adds the expected information.
   */
  public function testFormElement() {
    foreach (['node', 'taxonomy_term'] as $target_type) {
      $access = $this->createMock('\Drupal\Core\Access\AccessResultInterface');
      $access->method('isAllowed')->willReturn(TRUE);
      /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\ReferenceWidget $mock */
      $mock = $this->prepareFormElementMock($target_type);
      $mock->method('getCreateAccess')->willReturn($access);
      /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\WidgetBase $parent */
      $parent = $this->prepareFormElementMock($target_type, 'Drupal\select_or_other\Plugin\Field\FieldWidget\WidgetBase');

      $entity = $this->createMock('Drupal\Core\Entity\FieldableEntityInterface');
      $items = $this->createMock('Drupal\Core\Field\FieldItemListInterface');
      $items->method('getEntity')->willReturn($entity);
      /** @var \Drupal\Core\Field\FieldItemListInterface $items */
      $delta = 1;
      $element = [];
      $form = [];
      $form_state = new FormState();

      $parent_result = $parent->formElement($items, $delta, $element, $form, $form_state);
      $result = $mock->formElement($items, $delta, $element, $form, $form_state);
      $added = array_diff_key($result, $parent_result);

      $expected = [
        '#target_type' => $target_type,
        '#selection_handler' => 'some_handler',
        '#selection_settings' => [],
        '#autocreate' => [
          'bundle' => 'autoCreateBundle',
          'uid' => 1,
        ],
        '#validate_reference' => TRUE,
        '#tags' => $target_type === 'taxonomy_term',
        '#merged_values' => TRUE,
        '#element_validate' => [
          [
            get_class($mock),
            'validateReferenceWidget',
          ],
        ],
      ];

      $this->assertEquals($expected, $added);
    }
  }

  /**
   * Tests preparation for EntityAutocomplete::validateEntityAutocomplete.
   */
  public function testPrepareElementValuesForValidation() {
    $method = new \ReflectionMethod($this->getTestedClassName(), 'prepareElementValuesForValidation');
    $method->setAccessible(TRUE);

    foreach ([FALSE, TRUE] as $tags) {
      $element = $original_element = [
        '#tags' => $tags,
        '#value' => [
          'Some value',
          'Another value',
        ],
      ];
      $method->invokeArgs(NULL, [& $element]);

      if ($tags) {
        $this->assertTrue(is_string($element['#value']));
      }
      else {
        $this->assertEquals($original_element, $element);
      }
    }
  }

  /**
   * Tests if the widget correctly determines if it is applicable.
   */
  public function testIsApplicable() {
    $entity_reference_selection = $this->createMock('Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager');
    $entity_reference_selection->expects($this->exactly(4))
      ->method('getInstance')
      ->willReturnOnConsecutiveCalls(
        $this->createMock('Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface'),
        $this->createMock('Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface'),
        $this->createMock('Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface'),
        $this->createMock('Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface')
      );
    $this->registerServiceWithContainerMock('plugin.manager.entity_reference_selection', $entity_reference_selection);

    $definition = $this->getMockBuilder('Drupal\Core\Field\FieldDefinitionInterface')
      ->getMock();
    $definition->expects($this->exactly(4))
      ->method('getSettings')
      ->willReturnOnConsecutiveCalls(
        [],
        [],
        ['handler_settings' => ['auto_create' => TRUE]],
        ['handler_settings' => ['auto_create' => TRUE]]
      );
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
    $this->assertFalse(ReferenceWidget::isApplicable($definition));
    $this->assertFalse(ReferenceWidget::isApplicable($definition));
    $this->assertFalse(ReferenceWidget::isApplicable($definition));
    $this->assertTrue(ReferenceWidget::isApplicable($definition));
  }

  /**
   * Tests if the selected options are properly prepared.
   */
  public function testPrepareSelectedOptions() {
    $entity_id = 1;
    $entity_label = 'Label';
    $entity_mock = $this->createMock('\Drupal\Core\Entity\EntityBase');
    $entity_mock->expects($this->any())
      ->method('id')
      ->willReturn($entity_id);
    $entity_mock->expects($this->any())
      ->method('label')
      ->willReturn($entity_label);

    $entity_storage_mock = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
    $entity_storage_mock->expects($this->exactly(2))
      ->method('loadMultiple')
      ->willReturnOnConsecutiveCalls([], [$entity_mock]);

    $mock = $this->mockBuilder->disableOriginalConstructor()
      ->onlyMethods(['getEntityStorage'])
      ->getMock();
    $mock->expects($this->exactly(2))
      ->method('getEntityStorage')
      ->willReturn($entity_storage_mock);

    $get_options = new \ReflectionMethod($mock, 'prepareSelectedOptions');
    $get_options->setAccessible(TRUE);

    // First invocation returns an empty array because there are no entities.
    $options = $get_options->invokeArgs($mock, [[]]);
    $expected = [];
    $this->assertEquals($options, $expected);

    // Second invocation returns a value array..
    $options = $get_options->invokeArgs($mock, [[]]);
    $expected = ["{$entity_label} ({$entity_id})"];
    $this->assertEquals($options, $expected);
  }

}

<?php

namespace Drupal\Tests\select_or_other\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget;

/**
 * Tests the form element implementation.
 *
 * @group select_or_other
 *
 * @covers Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget
 */
class ListWidgetTest extends UnitTestBase {

  /**
   * {@inheritDoc}
   */
  public function setUp() :void {
    parent::setUp();
    $this->currentUser = $this->getMockBuilder('Drupal\Core\Session\AccountInterface')->disableOriginalConstructor()->getMock();
    $this->entityTypeManager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManagerInterface')->getMock();
    $this->entityStorage = $this->getMockBuilder('Drupal\Core\Entity\EntityStorageInterface')->getMock();
    $this->field_storage_config = $this->getMockForAbstractClass('Drupal\field\FieldStorageConfigInterface');

  }

  /**
   * Method to set the return value of each mock.
   *
   * @param array $allowed_values
   *   Values of the allowed_values settings.
   */
  private function setMock(array $allowed_values = []) :void {
    $this->field_storage_config
      ->method('setSetting')
      ->with('allowed_values', $allowed_values)
      ->willReturnSelf();
    $this->entityStorage
      ->method('load')
      ->will(self::returnValue($this->field_storage_config));
    $this->entityTypeManager
      ->method('getStorage')
      ->will(self::returnValue($this->entityStorage));
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestedClassName() {
    return 'Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget';
  }

  /**
   * Test if defaultSettings() returns the correct keys.
   */
  public function testGetOptions() {
    $expected = [1, 2];
    $options_provider = $this->getMockForAbstractClass('Drupal\Core\TypedData\OptionsProviderInterface');
    $options_provider->method('getSettableOptions')->willReturn($expected);

    $storage_definition = $this->getMockForAbstractClass('Drupal\Core\Field\FieldStorageDefinitionInterface');
    $storage_definition->method('getOptionsProvider')
      ->willReturn($options_provider);

    $field_definition = $this->getMockForAbstractClass('Drupal\Core\Field\FieldDefinitionInterface');
    $field_definition->method('getFieldStorageDefinition')
      ->willReturn($storage_definition);
    $constructor_arguments = [
      '',
      '',
      $field_definition,
      [],
      [],
      $this->currentUser,
      $this->entityTypeManager,
    ];
    $mock = $this->mockBuilder->setConstructorArgs($constructor_arguments)
      ->onlyMethods([
        'getColumn',
      ])
      ->getMock();
    $mock->method('getColumn')->willReturn(['column']);

    $get_options = new \ReflectionMethod($mock, 'getOptions');
    $get_options->setAccessible(TRUE);

    $options = $get_options->invoke($mock, $this->getMockForAbstractClass('Drupal\Core\Entity\FieldableEntityInterface'));
    $this->assertEquals($expected, $options);
  }

  /**
   * Test if formElement() adds the expected information.
   */
  public function testFormElement() {
    [$parent, $mock] = $this->getBasicMocks();
    /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget $mock */
    /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\WidgetBase $parent */
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $items = $this->getMockForAbstractClass('Drupal\Core\Field\FieldItemListInterface');
    $delta = NULL;
    $element = [];
    $form = [];
    $form_state = new FormState();

    $parent_result = $parent->formElement($items, $delta, $element, $form, $form_state);
    $result = $mock->formElement($items, $delta, $element, $form, $form_state);
    $added = array_diff_key($result, $parent_result);

    $expected = [
      '#merged_values' => TRUE,
      '#original_options' => [],
      '#other_options' => [],
    ];

    $this->assertEquals($expected, $added);
  }

  /**
   * Tests that massage form values returns the values passed to it.
   */
  public function massageFormValuesReturnsValuesPassedToIt() {
    $sut = $this->getNewSubjectUnderTest();
    $form = [];
    $form_state = new FormState();
    /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget $mock */
    $test_values = [
      [],
      ['value'],
      ['multiple', 'values'],
    ];

    foreach ($test_values as $values) {
      $result = $sut->massageFormValues($values, $form, $form_state);
      $this->assertEquals($values, $result);
    }
  }

  /**
   * Tests that massage form values removes the select value if present.
   */
  public function massageFormValuesRemovesSelectValueIfPresent() {
    $sut = $this->getNewSubjectUnderTest();
    $form = [];
    $form_state = new FormState();
    /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget $mock */
    $result = $sut->massageFormValues(['select' => 'test'], $form, $form_state);
    $this->assertEquals([], $result);
  }

  /**
   * Tests that massage form values removes the other value if present.
   */
  public function massageFormValuesRemovesOtherValueIfPresent() {
    $sut = $this->getNewSubjectUnderTest();
    $form = [];
    $form_state = new FormState();
    /** @var \Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget $mock */
    $result = $sut->massageFormValues(['other' => 'test'], $form, $form_state);
    $this->assertEquals([], $result);
  }

  /**
   * Tests that massage form values adds the new values to the allowed values.
   *
   * @test
   * @covers Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget::extractNewValues
   * @covers Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget::AddNewValuesToAllowedValues
   */
  public function massageFormValuesAddsNewValuesToAllowedValues() {
    $allowed_values = ['t' => 'test'];
    $field_definition = $this->getMockForAbstractClass('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_definition->method('getSetting')->willReturn($allowed_values);
    $sut = $this->getNewSubjectUnderTest($field_definition);
    $this->setMock(['est' => 'est'], TRUE);

    $form = [];
    $form_state = new FormState();

    // First invocation does not call setSetting or save.
    $sut->massageFormValues(['t'], $form, $form_state);
    // Second invocation calls setSetting and save.
    $sut->massageFormValues(['t', 'est'], $form, $form_state);
  }

  /**
   * Tests massage form values do not adds other values to the allowed values.
   *
   * @test
   * @covers Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget::extractNewValues
   * @covers Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget::AddNewValuesToAllowedValues
   */
  public function massageFormValuesDoNotAddOtherValuesToAllowedValues() {
    $allowed_values = ['t' => 'test'];
    $field_definition = $this->getMockForAbstractClass('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_definition->method('getSetting')->willReturn($allowed_values);
    $sut = $this->getNewSubjectUnderTest($field_definition);

    $field_storage_config = $this->getMockForAbstractClass('\Drupal\field\FieldStorageConfigInterface');
    $field_storage_config->expects($this->never())->method('setSetting')->willReturnSelf();
    $field_storage_config->expects($this->never())->method('save');

    $entity_storage_methods = ['load' => $field_storage_config];

    $entity_type_manager_methods = ['getStorage' => $this->getMockForAbstractClassWithMethods('\Drupal\Core\Entity\EntityStorageInterface', $entity_storage_methods)];
    $entity_type_manager_mock = $this->getMockForAbstractClassWithMethods('\Drupal\Core\Entity\EntityTypeManagerInterface', $entity_type_manager_methods);
    $this->registerServiceWithContainerMock('entity_type.manager', $entity_type_manager_mock);

    $form = [];
    $form_state = new FormState();

    $sut->setSetting('add_other_value_to_allowed_values', FALSE);

    // First invocation does not call setSetting or save.
    $sut->massageFormValues(['t'], $form, $form_state);
    // Second invocation calls setSetting and save.
    $sut->massageFormValues(['t', 'est'], $form, $form_state);
  }

  /**
   * Creates a new subject under test.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|\PHPUnit\Framework\MockObject\MockObject|null $fieldDefinition
   *   The field definitions or NULL.
   *
   * @return \Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget
   *   The new subject under test.
   */
  protected function getNewSubjectUnderTest(FieldDefinitionInterface $fieldDefinition = NULL) {
    $widget_id = 'widget_id';
    $plugin_definition = 'plugin_definition';
    if (empty($fieldDefinition)) {
      $fieldDefinition = $this->getMockForAbstractClass('\Drupal\Core\Field\FieldDefinitionInterface');
    }
    $settings = [];
    $third_party_settings = [];
    return new ListWidget($widget_id, $plugin_definition, $fieldDefinition, $settings, $third_party_settings, $this->currentUser, $this->entityTypeManager);
  }

}

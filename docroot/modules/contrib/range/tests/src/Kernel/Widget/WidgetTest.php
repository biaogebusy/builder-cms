<?php

namespace Drupal\Tests\range\Kernel\Widget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\range\Traits\RangeTestTrait;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests field widget.
 *
 * @group range
 */
class WidgetTest extends KernelTestBase {

  use RangeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'entity_test',
    'user',
    'range',
  ];

  /**
   * Field type to test against.
   *
   * @var string
   */
  protected $fieldType;

  /**
   * Field name to test against.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installConfig(['field']);
    $this->installConfig(['text']);
    $this->installConfig(['range']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
  }

  /**
   * Tests widget.
   */
  public function testFieldWidget() {
    foreach ($this->getRangeFieldTypes() as $this->fieldType) {
      // Create field and set form display.
      $this->fieldName = $this->getFieldName($this->fieldType);
      $this->createField($this->fieldType);
      $this->createFormDisplay();
      $widget_settings = $this->getWidgetSettings();
      $this->setFormDisplayComponent($this->fieldType, $widget_settings);
      // Test field widget.
      $entity = EntityTest::create([]);
      $content = $this->renderEntityForm($entity);
      $this->assertFieldByXPath($this->constructRangeFieldXpath('from'));
      $this->assertFieldByXPath($this->constructRangeFieldXpath('to'));
      $field_settings = $this->getFieldSettings($this->fieldType);
      $this->assertStringContainsString($widget_settings['label']['from'], $content);
      $this->assertStringContainsString($widget_settings['label']['to'], $content);
      $this->assertStringContainsString($field_settings['field']['prefix'] . $field_settings['from']['prefix'], $content);
      $this->assertStringContainsString($field_settings['from']['suffix'], $content);
      $this->assertStringContainsString($field_settings['to']['prefix'], $content);
      $this->assertStringContainsString($field_settings['to']['suffix'] . $field_settings['field']['suffix'], $content);
      // Delete field.
      $this->deleteField();
    }
  }

  /**
   * Helper: Constructs an XPath for the given range field name.
   *
   * @param string $name
   *   Field name.
   *
   * @return string
   *   XPath for specified value.
   */
  protected function constructRangeFieldXpath($name) {
    $field_settings = $this->getFieldSettings($this->fieldType);
    $widget_settings = $this->getWidgetSettings();

    $xpath = '//input[@name=:name][@type=:type][@min=:min][@max=:max][@step=:step][@placeholder=:placeholder]';
    return $this->buildXPathQuery($xpath, [
      ':name' => "{$this->fieldName}[0][$name]",
      ':type' => 'number',
      ':min' => $field_settings['min'],
      ':max' => $field_settings['max'],
      ':step' => $this->getExpectedStepValue(),
      ':placeholder' => $widget_settings['placeholder'][$name],
    ]);
  }

  /**
   * Renders a given entity form.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   */
  protected function renderEntityForm(FieldableEntityInterface $entity) {
    $form = $this->container
      ->get('entity.form_builder')
      ->getForm($entity, 'default');
    return $this->render($form);
  }

  /**
   * Returns expected step value for a number form element.
   *
   * @return string
   *   Expected step value.
   */
  private function getExpectedStepValue() {
    switch ($this->fieldType) {
      case 'range_integer':
        return '1';

      case 'range_float':
        return 'any';

      case 'range_decimal':
        return '0.0001';
    }
  }

}

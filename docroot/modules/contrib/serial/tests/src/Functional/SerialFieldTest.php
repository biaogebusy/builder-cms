<?php

namespace Drupal\Tests\serial\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the creation of serial fields.
 *
 * @group serial
 */
class SerialFieldTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'field',
    'field_ui',
    'node',
    'entity_test',
    'serial',
  ];

  /**
   * A user with permission to create test entities.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * An array of display options to pass to EntityViewDisplay.
   *
   * @var array
   */
  protected $displayOptions;

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The current serial id to test on.
   *
   * @var int
   */
  protected $serialId;

  /**
   * The serial field used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->webUser = $this->drupalCreateUser([
      'access content',
      'view test entity',
      'administer entity_test content',
      'administer entity_test form display',
      'administer content types',
      'administer node fields',
    ]);
    $this->drupalLogin($this->webUser);

    $field_name = 'field_serial';
    $type = 'serial';
    $widget_type = 'serial_default_widget';
    $formatter_type = 'serial_default_formatter';

    // Add the serial field to the entity test.
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => $type,
      'settings' => [
        'start_value' => 1,
        'init_existing_entities' => FALSE,
      ],
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'label' => 'Serial',
      'bundle' => 'entity_test',
      'required' => TRUE,
    ]);
    $this->field->save();

    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($field_name, ['type' => $widget_type])
      ->save();

    $this->displayOptions = [
      'type' => $formatter_type,
      'label' => 'hidden',
    ];

    EntityViewDisplay::create([
      'targetEntityType' => $this->field->getTargetEntityTypeId(),
      'bundle' => $this->field->getTargetBundle(),
      'mode' => 'full',
      'status' => TRUE,
    ])->setComponent($field_name, $this->displayOptions)->save();
  }

  /**
   * Helper function for testSerialField().
   */
  public function testSerialField() {
    // Test the entity creation form.
    $this->drupalGet('entity_test/add');
    // Make sure the "serial_default_widget" widget is on the markup.
    $fields = $this->xpath('//div[contains(@class, "field--widget-serial-default-widget") and @id="edit-field-serial-wrapper"]');
    $this->assertEquals(1, count($fields));
    // Make sure that the widget is hidden on the entity creation form.
    $this->assertSession()->fieldNotExists('field_serial[0][value]');

    // Test basic definition of serial field on entity save.
    $edit = [];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // Make sure the entity was saved.
    preg_match('|entity_test/manage/(\d+)|', $this->getSession()
      ->getCurrentUrl(), $match);
    $id = $match[1];
    $this->assertSession()
      ->pageTextContains(sprintf('entity_test %s has been created.', $id));
    // Make sure the serial id is in the output.
    $this->serialId = 1;
    $this->drupalGet('entity_test/' . $id);
    $serial = $this->xpath('//div[contains(@class, "field__item") and text()="' . $this->serialId . '"]');
    $this->assertEquals(1, count($serial));
  }

  /**
   * Creates N entities and and checks the serial id for each.
   *
   * @param int $entities
   *   Number of entities for creation.
   */
  public function testSerialEntityCreation($entities = 15) {
    // Create N entities.
    $i = 0;
    while ($i < $entities) {
      $this->drupalGet('entity_test/add');
      $edit = [];
      $this->drupalPostForm(NULL, $edit, t('Save'));
      // Make sure the entity was saved.
      preg_match('|entity_test/manage/(\d+)|', $this->getSession()
        ->getCurrentUrl(), $match);
      $id = $match[1];
      $this->assertSession()
        ->pageTextContains(sprintf('entity_test %s has been created.', $id));
      // Make sure the serial id is in the output.
      $this->serialId++;
      $this->drupalGet('entity_test/' . $id);
      $serial = $this->xpath('//div[contains(@class, "field__item") and text()="' . $this->serialId . '"]');
      $this->assertEquals(1, count($serial));
      $i++;
    }
  }

}

<?php

namespace Drupal\Tests\serial\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Tests the creation of serial fields.
 *
 * @group serial
 */
class SerialFieldTest extends BrowserTestBase {

  use FieldUiTestTrait;
  use StringTranslationTrait;

  /**
   * Add a default theme as in https://www.drupal.org/node/3083055.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'node',
    'entity_test',
    'serial',
  ];

  /**
   * A user with permission to manage test entities.
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
   * The current serial id to test on.
   *
   * @var int
   */
  protected $serialId;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->webUser = $this->drupalCreateUser([
      'access content',
      'view test entity',
      'administer entity_test content',
      'administer entity_test fields',
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
    $bundle_path = 'entity_test/structure/entity_test';
    $storage_edit = [
      'settings[start_value]' => '1',
      'settings[init_existing_entities]' => '0',
    ];
    $field_edit = ['required' => TRUE];
    $this->fieldUIAddNewField($bundle_path, 'serial', 'Serial', $type, $storage_edit, $field_edit);
    $field = FieldConfig::load("entity_test.entity_test.$field_name");

    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($field_name, ['type' => $widget_type])
      ->save();

    $this->displayOptions = [
      'type' => $formatter_type,
      'label' => 'hidden',
    ];

    EntityViewDisplay::create([
      'targetEntityType' => $field->getTargetEntityTypeId(),
      'bundle' => $field->getTargetBundle(),
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
    $this->submitForm($edit, 'Save');
    // Make sure the entity was saved.
    preg_match('|entity_test/manage/(\d+)|', $this->getSession()->getCurrentUrl(), $match);
    $id = $match[1];
    $this->assertSession()->pageTextContains(sprintf('entity_test %s has been created.', $id));
    // Make sure the serial id is in the output.
    $this->serialId = 1;
    $this->drupalGet('entity_test/' . $id);
    $this->assertSession()->pageTextContains(sprintf('%s', $id));
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
      $this->submitForm($edit, 'Save');
      // Make sure the entity was saved.
      preg_match('|entity_test/manage/(\d+)|', $this->getSession()->getCurrentUrl(), $match);
      $id = $match[1];
      $this->assertSession()->pageTextContains(sprintf('entity_test %s has been created.', $id));
      // Make sure the serial id is in the output.
      $this->serialId++;
      $this->drupalGet('entity_test/' . $id);
      $this->assertSession()->pageTextContains(sprintf('%s', $id));
      $i++;
    }
  }

}

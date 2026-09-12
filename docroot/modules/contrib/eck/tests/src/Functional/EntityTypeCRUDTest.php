<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Tests if eck entity types are correctly created and updated.
 *
 * @group eck
 */
class EntityTypeCRUDTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * Test if creation of an entity does not result in mismatched definitions.
   */
  public function testEntityCreationDoesNotResultInMismatchedEntityDefinitions() {
    $this->createEntityType([], 'TestType');

    $this->assertNoMismatchedFieldDefinitions();
  }

  /**
   * Test if updating an entity type does not result in mismatched definitions.
   */
  public function testIfEntityUpdateDoesNotResultInMismatchedEntityDefinitions() {
    $this->createEntityType([], 'TestType');

    $routeArguments = ['eck_entity_type' => 'testtype'];
    $route = 'entity.eck_entity_type.edit_form';
    $edit = ['created' => FALSE];
    $this->drupalGet(Url::fromRoute($route, $routeArguments));
    $this->submitForm($edit, 'Update TestType');

    $this->assertNoMismatchedFieldDefinitions();
  }

  /**
   * Asserts that there are no mismatched definitions.
   */
  private function assertNoMismatchedFieldDefinitions() {
    $this->drupalGet(Url::fromRoute('system.status'));
    $this->assertSession()->responseNotContains('Mismatched entity and/or field definitions');
  }

  /**
   * Test if entity type names longer than 32 characters are caught in time.
   *
   * Drupal limits entity type names to 32 characters. This test ensures we also
   * enforce that to prevent a white screen of death when a user creates an
   * entity type with more than a 32-character long name.
   */
  public function testIfTooLongEntityTypeNamesAreCaughtInTime() {
    $this->createEntityType([], 'a27CharacterLongNameIssLong');
    $label = 'a28CharacterLongNameIsLonger';
    $edit = [
      'label' => $label,
      'id' => strtolower($label),
    ];

    $this->drupalGet(Url::fromRoute('eck.entity_type.add'));
    $this->submitForm($edit, 'Create entity type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("Machine name cannot be longer than <em class=\"placeholder\">27</em> characters but is currently <em class=\"placeholder\">28</em> characters long.");
  }

  /**
   * Test if entity type names that would cause collisions are caught in time.
   */
  public function testEntityTypeNameCollisions() {
    $edit = [
      'label' => 'Block',
      'id' => 'block',
    ];

    $this->drupalGet(Url::fromRoute('eck.entity_type.add'));
    $this->submitForm($edit, 'Create entity type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('The machine-readable name is already in use. It must be unique.');
  }

  /**
   * Test if entity type names that start with a number are caught in time.
   */
  public function testEntityTypeNameStartWithNumber() {
    $edit = [
      'label' => '2',
      'id' => '2',
    ];

    $this->drupalGet(Url::fromRoute('eck.entity_type.add'));
    $this->submitForm($edit, 'Create entity type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('The machine-readable name cannot start with a number.');
  }

}

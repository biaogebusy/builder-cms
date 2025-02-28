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
    $submitButton = $this->t('Update @type', ['@type' => 'TestType']);
    $this->drupalPostForm(Url::fromRoute($route, $routeArguments), $edit, $submitButton);

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
   * Drupal limits entity type names to 32 characters. This test ensures we also
   * enforce that to prevent a white screen of death when a user creates an
   * entity type with more than 32 character long name.
   */
  public function testIfTooLongEntityTypeNamesAreCaughtInTime() {
    $this->createEntityType([], 'a27CharacterLongNameIssLong');
    $label = 'a28CharacterLongNameIsLonger';
    $edit = [
      'label' => $label,
      'id' => $id = strtolower($label),
    ];

    $this->drupalPostForm(Url::fromRoute('eck.entity_type.add'), $edit, $this->t('Create entity type'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("Machine name cannot be longer than <em class=\"placeholder\">27</em> characters but is currently <em class=\"placeholder\">28</em> characters long.");
  }

}

<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Tests the functioning of eck's dynamic base fields.
 *
 * @group eck
 */
class DynamicBaseFieldTest extends FunctionalTestBase {

  /**
   * Test the creation, update and deletion of entity base fields.
   */
  public function testBaseFieldCrud() {
    // Create the entity type.
    $type = $this->createEntityType(['uid', 'created', 'changed', 'status']);
    $bundle = $this->createEntityBundle($type['id']);

    // Make sure base fields are added.
    $route_args = [
      'eck_entity_type' => $type['id'],
      'eck_entity_bundle' => $bundle['type'],
    ];
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldExists('uid[0][target_id]');
    $this->assertSession()->fieldExists('created[0][value][date]');
    $this->assertSession()->fieldExists('status[value]');

    // Add a field to the entity type.
    $edit = ['title' => TRUE];
    $this->drupalGet(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]));
    $this->submitForm($edit, 'Update ' . $type['label']);
    $this->assertSession()->responseContains((string) $this->t('Entity type %label has been updated.', ['%label' => $type['label']]));

    // Make sure the field was added.
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldExists('title[0][value]');

    // Remove a field from the entity type.
    $edit = ['created' => FALSE];
    $this->drupalGet(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]));
    $this->submitForm($edit, 'Update ' . $type['label']);
    $this->assertSession()->responseContains((string) $this->t('Entity type %label has been updated.', ['%label' => $type['label']]));

    // Make sure the base field was removed.
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldNotExists('created[0][value][date]');

    // Remove 'status' field from entity type.
    $edit = ['status' => FALSE];
    $this->drupalGet(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]));
    $this->submitForm($edit, (string) t('Update @type', ['@type' => $type['label']]));
    $this->assertSession()->responseContains((string) t('Entity type %label has been updated.', ['%label' => $type['label']]));

    // Check if 'status' field really removed.
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldNotExists('status[value]');

    // Add an entity to make sure there is data in the title field.
    $edit = ['title[0][value]' => $this->randomMachineName()];
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains($edit['title[0][value]']);

    // We should not be able to remove fields that have data.
    $this->drupalGet(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]));
    $fields = $this->xpath('//input[@type="checkbox"][@disabled]');
    $this->assertTrue(\count($fields) > 0);
  }

  /**
   * Test if entities can be created when base fields are configured.
   */
  public function testEntityEntityCreation() {
    $type = $this->createEntityType();
    $bundle = $this->createEntityBundle($type['id']);

    // Test if a new entity can be created.
    $edit = ['title[0][value]' => $this->randomMachineName()];
    $route_args = [
      'eck_entity_type' => $type['id'],
      'eck_entity_bundle' => $bundle['type'],
    ];
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains($edit['title[0][value]']);
  }

  /**
   * Test title and description overrides for base fields.
   */
  public function testTitlesDescriptionOverrides() {
    $entity_type = $this->createEntityType();
    $bundle = $this->createEntityBundle($entity_type['id']);

    $new_eck_entity_page = Url::fromRoute('eck.entity.add', [
      'eck_entity_type' => $entity_type['id'],
      'eck_entity_bundle' => $bundle['type'],
    ]);

    $this->drupalGet($new_eck_entity_page);
    // Verify default field title.
    $this->assertSession()->pageTextContains('Title');
    // Verify default field description.
    $this->assertSession()->pageTextContains('The title of the entity.');

    // Override default title and description.
    $edit['title_title_override'] = 'First name';
    $edit['title_description_override'] = 'Please enter your first name.';
    $this->drupalGet(Url::fromRoute("entity.{$entity_type['id']}_type.edit_form", ["{$entity_type['id']}_type" => $bundle['type']]));
    $this->submitForm($edit, 'Save bundle');

    $this->drupalGet($new_eck_entity_page);
    // Verify overridden field title.
    $this->assertSession()->pageTextContains('First name');
    // Verify overridden field description.
    $this->assertSession()->pageTextContains('Please enter your first name.');
  }

}

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
  public function testBaseFieldCRUD() {
    // Create the entity type.
    $type = $this->createEntityType(['uid', 'created', 'changed']);
    $bundle = $this->createEntityBundle($type['id']);

    // Make sure base fields are added.
    $route_args = [
      'eck_entity_type' => $type['id'],
      'eck_entity_bundle' => $bundle['type'],
    ];
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldExists('uid[0][target_id]');
    $this->assertSession()->fieldExists('created[0][value][date]');

    // Add a field to the entity type.
    $edit = ['title' => TRUE];
    $this->drupalPostForm(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]), $edit, $this->t('Update @type', ['@type' => $type['label']]));
    $this->assertSession()->responseContains((string) $this->t('Entity type %label has been updated.', ['%label' => $type['label']]));

    // Make sure the field was added.
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldExists('title[0][value]');

    // Remove a field from the entity type.
    $edit = ['created' => FALSE];
    $this->drupalPostForm(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]), $edit, $this->t('Update @type', ['@type' => $type['label']]));
    $this->assertSession()->responseContains((string) $this->t('Entity type %label has been updated.', ['%label' => $type['label']]));

    // Make sure the base field was removed.
    $this->drupalGet(Url::fromRoute('eck.entity.add', $route_args));
    $this->assertSession()->fieldNotExists('created[0][value][date]');

    // Add an entity to make sure there is data in the title field.
    $edit = ['title[0][value]' => $this->randomMachineName()];
    $this->drupalPostForm(Url::fromRoute('eck.entity.add', $route_args), $edit, $this->t('Save'));
    $this->assertSession()->responseContains($edit['title[0][value]']);

    // We should not be able to remove fields that have data.
    $this->drupalGet(Url::fromRoute('entity.eck_entity_type.edit_form', ['eck_entity_type' => $type['id']]));
    $fields = $this->xpath('//input[@type="checkbox"][@disabled]');
    $this->assertTrue(count($fields) > 0);
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
    $this->drupalPostForm(Url::fromRoute('eck.entity.add', $route_args), $edit, $this->t('Save'));
    $this->assertSession()->responseContains($edit['title[0][value]']);
  }

}

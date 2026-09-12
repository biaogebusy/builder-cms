<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Tests if eck entities are correctly created and updated.
 *
 * @group eck
 */
class EntityCRUDTest extends FunctionalTestBase {

  /**
   * Tests if new entities can be created successfully.
   *
   * @test
   */
  public function newEntitiesCanBeCreated() {
    $entityTypeInfo = $this->createEntityType(['title'], 'TestType');
    $bundleInfo = $this->createEntityBundle($entityTypeInfo['id'], 'TestBundle');

    $params = [
      'eck_entity_type' => $entityTypeInfo['id'],
      'eck_entity_bundle' => $bundleInfo['type'],
    ];
    $url = Url::fromRoute('eck.entity.add', $params);
    $values = ['title[0][value]' => 'testEntity'];
    $this->drupalGet($url);
    $this->submitForm($values, 'Save');

    $currentUrl = $this->getSession()->getCurrentUrl();
    $entity = \Drupal::entityTypeManager()->getStorage($entityTypeInfo['id'])->load(1);
    $entityUrl = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $this->assertSame($entityUrl, $currentUrl);
  }

  /**
   * Entities can be created and edited with title overrides.
   *
   * @test
   */
  public function entitiesCanBeCreatedAndEdited() {
    $entityTypeInfo = $this->createEntityType();
    $entity_type = $entityTypeInfo['id'];

    $title_overrides = [];
    foreach ($this->getConfigurableBaseFields() as $field) {
      $title_overrides[$field] = $this->randomMachineName(16);
    }

    $bundleInfo = $this->createEntityBundle($entity_type, NULL, $title_overrides);

    $params = [
      'eck_entity_type' => $entityTypeInfo['id'],
      'eck_entity_bundle' => $bundleInfo['type'],
    ];

    $url = Url::fromRoute('eck.entity.add', $params);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    foreach ($title_overrides as $field => $title_override) {
      if ($field === 'changed') {
        // Changed does not appear on the edit form.
        $this->assertSession()->responseNotContains($title_override);
      }
      else {
        // Other base fields appear on the edit form.
        $this->assertSession()->responseContains($title_override);
      }
    }

    $values = [
      'title[0][value]' => $this->randomMachineName(16),
      'created[0][value][date]' => '2010-01-02',
      'created[0][value][time]' => '15:24:59',
    ];

    // Create entity.
    $this->submitForm($values, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    $view_url = $this->getSession()->getCurrentUrl();
    $matches = [];
    $this->assertEquals(1, preg_match("@/{$entity_type}/(\d+)$@", $view_url, $matches));
    $entity_id = $matches[1];

    $edit_url = Url::fromRoute("entity.{$entity_type}.edit_form", [$entity_type => $entity_id]);

    // Edit entity.
    $this->drupalGet($edit_url);
    $this->assertSession()->statusCodeEquals(200);

    foreach ($values as $value) {
      $this->assertSession()->responseContains($value);
    }

    // Save entity with different values.
    $values = [
      'title[0][value]' => $this->randomMachineName(16),
      'created[0][value][date]' => '2015-05-31',
      'created[0][value][time]' => '02:37:10',
    ];

    $this->submitForm($values, 'Save');
    $this->drupalGet($edit_url);

    foreach ($values as $value) {
      $this->assertSession()->responseContains($value);
    }
  }

  /**
   * Test that attempting to create a non-existing entity type results in a 404.
   *
   * @test
   */
  public function attemptedCreationOfNonExistingEntityTypeResultsIn404() {
    $params = [
      'eck_entity_type' => 'non-existing',
      'eck_entity_bundle' => 'non-existing',
    ];
    $url = Url::fromRoute('eck.entity.add', $params);

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that attempting to create a non-existing bundle results in a 404.
   *
   * @test
   */
  public function attemptedCreationOfNonExistingBundleResultsIn404() {
    $this->createEntityType([], 'TestType');
    $params = [
      'eck_entity_type' => 'testtype',
      'eck_entity_bundle' => 'non-existing',
    ];
    $url = Url::fromRoute('eck.entity.add', $params);

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(404);
  }

}

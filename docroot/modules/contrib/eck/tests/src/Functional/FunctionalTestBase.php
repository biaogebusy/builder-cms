<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides common functionality for ECK functional tests.
 */
abstract class FunctionalTestBase extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eck'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return [
      'administer eck entity types',
      'administer eck entities',
      'administer eck entity bundles',
      'bypass eck entity access',
    ];
  }

  /**
   * Creates an entity type with a given label and/or enabled base fields.
   *
   * @param array $fields
   *   The fields that should be enabled for this entity type.
   * @param string $label
   *   The name of the entity type.
   *
   * @return array
   *   Information about the created entity type.
   *   - id:    the type's machine name
   *   - label: the type's label.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function createEntityType(array $fields = [], $label = '') {
    $label = empty($label) ? $this->randomMachineName() : $label;
    $fields = empty($fields) ? $this->getConfigurableBaseFields() : $fields;

    $edit = [
      'label' => $label,
      'id' => $id = strtolower($label),
    ];

    foreach ($fields as $field) {
      $edit[$field] = TRUE;
    }
    $this->drupalGet(Url::fromRoute('eck.entity_type.add'));
    $this->submitForm($edit, 'Create entity type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("Entity type <em class=\"placeholder\">$label</em> has been added.");

    // Clear entity definitions cache to find definition of our new entity type.
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    return ['id' => $id, 'label' => $label];
  }

  /**
   * Returns an array of the configurable base fields.
   *
   * @return array
   *   The machine names of the configurable base fields.
   */
  protected function getConfigurableBaseFields() {
    return ['created', 'changed', 'uid', 'title', 'status'];
  }

  /**
   * Adds a bundle for a given entity type.
   *
   * @param string $entity_type
   *   The entity type to add the bundle for.
   * @param string $label
   *   The bundle label.
   * @param array $title_overrides
   *   A key / value array of title overrides.
   *
   * @return array
   *   The machine name and label of the new bundle.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function createEntityBundle($entity_type, $label = '', $title_overrides = []) {
    if (empty($label)) {
      $label = $this->randomMachineName();
    }
    $bundle = strtolower($label);

    $edit = [
      'name' => $label,
      'type' => $bundle,
    ];

    foreach ($title_overrides as $field => $title_override) {
      $edit[$field . '_title_override'] = $title_override;
    }

    $this->drupalGet(Url::fromRoute("eck.entity.{$entity_type}_type.add"));
    $this->submitForm($edit, 'Save bundle');
    $this->assertSession()->responseContains("The entity bundle <em class=\"placeholder\">$label</em> has been added.");

    return $edit;
  }

  /**
   * Edits a bundle for a given entity type.
   *
   * @param string $entity_type
   *   The entity type to add the bundle for.
   * @param string $bundle
   *   The bundle type.
   * @param string $label
   *   The bundle label.
   * @param array $title_overrides
   *   A key / value array of title overrides.
   *
   * @return array
   *   The machine name and label of the new bundle.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function editEntityBundle($entity_type, $bundle, $label, $title_overrides = []) {
    $this->drupalGet(Url::fromRoute("entity.{$entity_type}_type.edit_form", ["{$entity_type}_type" => $bundle]));
    $this->assertSession()->statusCodeEquals(200);

    $edit = ['name' => $label];

    foreach ($title_overrides as $field => $title_override) {
      $edit[$field . '_title_override'] = $title_override;
    }

    $this->submitForm($edit, 'Save bundle');
    $this->assertSession()->responseContains("The entity bundle <em class=\"placeholder\">$label</em> has been updated.");
    return $edit;
  }

  /**
   * Creates a new entity.
   *
   * @param string $entity_type
   *   The entity type to be created.
   * @param array $values
   *   An array of settings.
   *   Example: 'id' => 'foo'.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A new entity.
   */
  protected function createEntity($entity_type, array $values) {
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);
    $entity = $storage->create($values);
    $entity->save();
    return $entity;
  }

}

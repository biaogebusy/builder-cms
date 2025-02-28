<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides common functionality for ECK functional tests.
 */
abstract class FunctionalTestBase extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'eck'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $permissions = [
      'administer eck entity types',
      'administer eck entities',
      'administer eck entity bundles',
      'bypass eck entity access',
    ];
    $user = $this->createUser($permissions);
    $this->drupalLogin($user);
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

    $this->drupalPostForm(Url::fromRoute('eck.entity_type.add'), $edit, $this->t('Create entity type'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("Entity type <em class=\"placeholder\">$label</em> has been added.");
    return ['id' => $id, 'label' => $label];
  }

  /**
   * Returns an array of the configurable base fields.
   *
   * @return array
   *   The machine names of the configurable base fields.
   */
  protected function getConfigurableBaseFields() {
    return ['created', 'changed', 'uid', 'title'];
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

    $this->drupalPostForm(Url::fromRoute("eck.entity.{$entity_type}_type.add"), $edit, $this->t('Save bundle'));
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

    foreach ($title_overrides as $field =>  $title_override) {
      $edit[$field . '_title_override'] = $title_override;
    }

    $this->drupalPostForm(NULL, $edit, $this->t('Save bundle'));
    $this->assertSession()->responseContains("The entity bundle <em class=\"placeholder\">$label</em> has been updated.");
  }

}

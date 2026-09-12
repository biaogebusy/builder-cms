<?php

namespace Drupal\Tests\eck\Functional;

/**
 * Tests translating ECK entities.
 *
 * @group eck
 */
class EckEntityTranslationTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_translation'];

  /**
   * The ECK entity type.
   *
   * @var array
   *   Information about the created entity type.
   */
  protected $entityType;

  /**
   * The ECK bundle.
   *
   * @var array
   *   Information about the created bundle.
   */
  protected $bundle;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer languages',
      'administer content translation',
      'translate any entity',
      'create content translations',
      'update content translations',
      'delete content translations',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create new entity type.
    $this->entityType = $this->createEntityType([], 'translatable');
    $this->bundle = $this->createEntityBundle($this->entityType['id'], 'translatable');

    // Create one more entity type without title field.
    $this->createEntityType(['uid'], 'no_title');
    $this->createEntityBundle('no_title', 'no_title');

    // Add one more language.
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm(['predefined_langcode' => 'uk'], 'Add language');

    // Enable content translation on newly created entity type.
    $this->drupalGet('admin/config/regional/content-language');
    $edit = [
      "entity_types[{$this->entityType['id']}]" => TRUE,
      "entity_types[no_title]" => TRUE,
      "settings[{$this->entityType['id']}][{$this->bundle['type']}][translatable]" => TRUE,
      "settings[no_title][no_title][translatable]" => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');
    // Adding languages requires a container rebuild in the test running
    // environment so that multilingual services are used.
    $this->resetAll();
  }

  /**
   * Test translating of ECK entities.
   */
  public function testEntityTranslation() {
    $entity = $this->createEntity($this->entityType['id'], [
      'type' => $this->bundle['type'],
      'title' => 'ECK Entity',
    ]);
    $entity_type = $entity->getEntityTypeId();

    // Add translation.
    $this->drupalGet("$entity_type/{$entity->id()}/translations");
    $this->assertSession()->linkByHrefExists("$entity_type/{$entity->id()}/translations/add/en/uk");
    $this->getSession()->getPage()->clickLink('Add');
    // Verify page title.
    $this->assertSession()->responseContains('Create <em class="placeholder">Ukrainian</em> translation of <em class="placeholder">ECK Entity</em>');
    // Save translation.
    $this->getSession()->getPage()->fillField('Title', 'ECK Entity translation');
    $this->getSession()->getPage()->pressButton('Save');

    // Make sure the created translation exists.
    $this->drupalGet("$entity_type/{$entity->id()}/translations");
    $this->assertSession()->linkByHrefExists("$entity_type/{$entity->id()}");
    $this->assertSession()->linkByHrefExists("uk/$entity_type/{$entity->id()}");

    $this->drupalGet("$entity_type/{$entity->id()}");
    $this->assertSession()->pageTextContains('ECK Entity');

    $this->drupalGet("uk/$entity_type/{$entity->id()}");
    $this->assertSession()->pageTextContains('ECK Entity translation');

    // Verify page title of translation edit.
    $this->drupalGet("uk/$entity_type/{$entity->id()}/edit");
    $this->assertSession()->responseContains('<em>Edit translatable</em> ECK Entity translation [<em class="placeholder">Ukrainian</em> translation]');

    // Verify page titles for entity types without title field.
    $entity2 = $this->createEntity('no_title', [
      'type' => 'no_title',
    ]);
    $this->drupalGet("no_title/{$entity2->id()}/translations/add/en/uk");
    $this->assertSession()->responseContains('Create <em class="placeholder">Ukrainian</em> translation of');
    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalGet("uk/no_title/{$entity2->id()}/edit");
    $this->assertSession()->responseContains('<em>Edit no_title</em>  [<em class="placeholder">Ukrainian</em> translation]');
  }

  /**
   * Test the delete process of ECK entity translations.
   */
  public function testDeleteEntityTranslation() {
    $entity = $this->createEntity($this->entityType['id'], [
      'type' => $this->bundle['type'],
      'title' => 'ECK Entity',
    ]);
    $entity_type = $entity->getEntityTypeId();

    // Add entity translation.
    $this->drupalGet("$entity_type/{$entity->id()}/translations/add/en/uk");
    $this->getSession()->getPage()->fillField('Title', 'ECK Entity translation');
    $this->getSession()->getPage()->pressButton('Save');

    // Remove newly created translation.
    $this->drupalGet("uk/$entity_type/{$entity->id()}/edit");
    $this->getSession()->getPage()->clickLink('Delete translation');

    $this->assertSession()->pageTextContains('Are you sure you want to delete the Ukrainian translation of the translatable ECK Entity translation?');
    $this->getSession()->getPage()->pressButton('Delete Ukrainian translation');

    $this->drupalGet("$entity_type/{$entity->id()}/translations");

    // Make sure the translation is removed and original entity exists.
    $this->assertSession()->linkByHrefExists("$entity_type/{$entity->id()}");
    $this->assertSession()->linkByHrefExists("uk/$entity_type/{$entity->id()}/translations/add/en/uk");
  }

}

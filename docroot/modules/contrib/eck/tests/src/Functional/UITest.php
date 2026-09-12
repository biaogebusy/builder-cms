<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Tests if eck's UI elements are working properly.
 *
 * @group eck
 *
 * @codeCoverageIgnore because we don't have to test the tests
 */
class UITest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Place the actions block, to test if the actions are placed correctly.
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Makes sure the Add entity type actions are properly implemented.
   */
  public function testAddEntityTypeActions() {
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->assertSession()->linkExists($this->t('Add entity type'));
  }

  /**
   * Makes sure the listing titles of entity type listings are correct.
   */
  public function testListingTitles() {
    $type = $this->createEntityType();
    $bundle = $this->createEntityBundle($type['id']);
    $this->drupalPlaceBlock('page_title_block');

    // Test title of the entity types listing.
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->assertSession()->responseContains("ECK Entity Types");

    // Test title of the entity bundles listing.
    $this->drupalGet(Url::fromRoute("eck.entity.{$type['id']}_type.list"));
    $this->assertSession()->responseContains((string) $this->t('%type bundles', ['%type' => ucfirst($type['label'])]));

    // Test title of the add bundle page.
    $this->drupalGet(Url::fromRoute("eck.entity.{$type['id']}_type.add"));
    $this->assertSession()->responseContains((string) $this->t('Add %type bundle', ['%type' => $type['label']]));

    // Test title of the edit bundle page.
    $this->drupalGet(Url::fromRoute("entity.{$type['id']}_type.edit_form", ["{$type['id']}_type" => $bundle['type']]));
    $this->assertSession()->responseContains((string) $this->t('Edit %type bundle', ['%type' => $type['label']]));

    // Test title of the delete bundle page.
    $this->drupalGet(Url::fromRoute("entity.{$type['id']}_type.delete_form", ["{$type['id']}_type" => $bundle['type']]));
    $this->assertSession()->responseContains((string) $this->t('Are you sure you want to delete the entity bundle %type?', ['%type' => $bundle['name']]));

    // Test title of the entity content listing.
    $this->drupalGet(Url::fromRoute("eck.entity.{$type['id']}.list"));
    $this->assertSession()->responseContains((string) $this->t('%type content', ['%type' => ucfirst($type['label'])]));
  }

  /**
   * Makes sure the operations on the entity type listing page work as expected.
   */
  public function testEntityTypeListingOperations() {
    $entityTypeManager = \Drupal::entityTypeManager();
    $entity = $entityTypeManager->getDefinition('eck_entity_type');
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $noEntitiesYetText = (string) $this->t('There are no @label entities yet.', ['@label' => strtolower($entity->getLabel())]);
    $this->assertSession()->responseContains($noEntitiesYetText);

    $entityType = $this->createEntityType();
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->assertSession()->responseNotContains($noEntitiesYetText);
    foreach (['Add content', 'Content list'] as $option) {
      $this->assertSession()->linkNotExists($option, $this->t('No %option option is shown when there are no bundles.', ['%option' => $option]));
    }
    $this->assertSession()->linkExists($this->t('Add bundle'));
    $this->assertSession()->linkExists($this->t('Bundle list'));
    $this->assertSession()->linkExists($this->t('Edit'));
    $this->assertSession()->linkExists($this->t('Delete'));

    $bundles[] = $this->createEntityBundle($entityType['id'], $entityType['id']);
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->assertSession()->responseNotContains($noEntitiesYetText);
    $this->assertSession()->linkNotExists($this->t('Content list'), $this->t('No %option option is shown when there is no content.', ['%option' => $this->t('Content list')]));
    $this->assertSession()->linkExists($this->t('Add content'));
    $this->assertSession()->linkExists($this->t('Bundle list'));
    $this->assertSession()->linkExists($this->t('Edit'));
    $this->assertSession()->linkExists($this->t('Delete'));

    // Since there is only one bundle. The add content link should point
    // directly to the correct add entity form. We should be able to add a new
    // entity directly after clicking the link.
    $this->clickLink($this->t('Add content'));
    $this->submitForm(['title[0][value]' => $this->randomMachineName()], 'Save');
    // There is now content in the database, which means the content list link
    // should also be displayed.
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->assertSession()->responseNotContains($noEntitiesYetText);
    $this->assertSession()->linkExists($this->t('Content list'));
    $this->assertSession()->linkExists($this->t('Add content'));
    $this->assertSession()->linkExists($this->t('Bundle list'));
    $this->assertSession()->linkExists($this->t('Edit'));
    $this->assertSession()->linkExists($this->t('Delete'));

    // If there are multiple bundles, clicking the add Content button should end
    // up with a choice between all available bundles.
    $bundles[] = $this->createEntityBundle($entityType['id']);
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->clickLink($this->t('Add content'));
    foreach ($bundles as $bundle) {
      $this->assertSession()->responseContains($bundle['name']);
    }
  }

  /**
   * Tests that the entity listing contains the correct local actions.
   */
  public function testAddEntityActions() {
    $entityType = $this->createEntityType();
    // No content can be added without the bundle, the link should therefor not
    // be present if there are no bundles.
    $this->drupalGet(Url::fromRoute("eck.entity.{$entityType['id']}.list"));
    $this->assertSession()->responseNotContains("Add {$entityType['label']}");

    $bundles[] = $this->createEntityBundle($entityType['id']);
    // The action link should link directly to the add entity form if there is
    // only one bundle present.
    $this->drupalGet(Url::fromRoute("eck.entity.{$entityType['id']}.list"));
    $this->clickLink("Add {$entityType['label']}");
    $this->assertSession()->fieldExists('title[0][value]');

    $bundles[] = $this->createEntityBundle($entityType['id']);
    // When there are multiple bundles available. The user should be able to
    // choose which bundle to use.
    $this->drupalGet(Url::fromRoute("eck.entity.{$entityType['id']}.list"));
    $this->clickLink("Add {$entityType['label']}");
    foreach ($bundles as $bundle) {
      $this->assertSession()->responseContains($bundle['name']);
    }

    // After deleting the bundle, the user should once again end up on the add
    // entity form when clicking the action link.
    $route = "entity.{$entityType['id']}_type.delete_form";
    $routeArguments = ["{$entityType['id']}_type" => $bundles[1]['type']];
    $this->drupalGet(Url::fromRoute($route, $routeArguments));
    $this->submitForm([], 'Delete');
    $this->drupalGet(Url::fromRoute("eck.entity.{$entityType['id']}.list"));
    $this->clickLink("Add {$entityType['label']}");
    $this->assertSession()->fieldExists('title[0][value]');
  }

  /**
   * Tests "Use the administration theme when editing or creating ECK entities".
   */
  public function testAdministrationEckThemeSettings() {
    $admin_user = $this->drupalCreateUser([
      'administer eck entity types',
      'administer eck entities',
      'administer eck entity bundles',
      'access administration pages',
      'view the administration theme',
      'administer themes',
    ]);
    $this->drupalLogin($admin_user);

    $type = $this->createEntityType();
    $bundle = $this->createEntityBundle($type['id']);

    $this->drupalGet(Url::fromRoute('eck.entity.add', [
      'eck_entity_type' => $type['id'],
      'eck_entity_bundle' => $bundle['type'],
    ]));
    $this->assertSession()->responseContains('core/themes/starterkit_theme');

    // Install an administration theme and enable "Use the administration theme
    // when editing or creating ECK entities" option.
    $this->container->get('theme_installer')->install(['claro']);
    $edit = [
      'admin_theme' => 'claro',
      'eck_use_admin_theme' => TRUE,
    ];
    $this->drupalGet('admin/appearance');
    $this->submitForm($edit, 'Save configuration');

    $this->drupalGet(Url::fromRoute('eck.entity.add', [
      'eck_entity_type' => $type['id'],
      'eck_entity_bundle' => $bundle['type'],
    ]));
    $this->assertSession()->responseContains('core/themes/claro');
  }

}

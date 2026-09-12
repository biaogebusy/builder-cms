<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Tests eck's access control.
 *
 * @group eck
 */
class AccessTest extends FunctionalTestBase {

  /**
   * Information about the entity type we are using for testing.
   *
   * @var array
   * @see \Drupal\Tests\eck\Functional\FunctionalTestBase::createEntityType()
   */
  protected $entityTypeInfo;

  /**
   * Information about the bundle we are using for testing.
   *
   * @var array
   * @see \Drupal\Tests\eck\Functional\FunctionalTestBase::createEntityBundle()
   */
  protected $bundleInfo;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->entityTypeInfo = $this->createEntityType();
    $this->bundleInfo = $this->createEntityBundle($this->entityTypeInfo['id']);
    // Start testing with a logged out user.
    $this->drupalLogout();
  }

  /**
   * Tests if the access to the default routes is properly checked.
   */
  public function testDefaultRoutes() {
    $routes = [
      'administer eck entity types' => [
        'eck.entity_type.list',
        'eck.entity_type.add',
        'entity.eck_entity_type.edit_form',
        'entity.eck_entity_type.delete_form',
      ],
      "create {$this->entityTypeInfo['id']} entities" => [
        'eck.entity.add_page',
        'eck.entity.add',
      ],
    ];
    $route_args = [
      'eck_entity_type' => $this->entityTypeInfo['id'],
      'eck_entity_bundle' => $this->bundleInfo['type'],
    ];
    foreach ($routes as $route_names) {
      foreach ($route_names as $route) {
        $this->drupalGet(Url::fromRoute($route, $route_args));
        // Anonymous users can not access the route.
        $this->assertSession()->statusCodeEquals(403);
      }
    }

    \Drupal::entityTypeManager()->clearCachedDefinitions();
    foreach ($routes as $permission => $route_names) {
      $this->drupalLogin($this->drupalCreateUser([$permission]));
      foreach ($route_names as $route) {
        $this->drupalGet(Url::fromRoute($route, $route_args));
        // Users with the correct permission can access the route.
        $this->assertSession()->statusCodeEquals(200);
      }
    }
  }

  /**
   * Tests if the access to dynamic routes is properly checked.
   */
  public function testDynamicRoutes() {
    $routes = [
      "access {$this->entityTypeInfo['id']} entity listing" => [
        "eck.entity.{$this->entityTypeInfo['id']}.list",
      ],
      'bypass eck entity access' => [
        "eck.entity.{$this->entityTypeInfo['id']}.list",
      ],
      'administer eck entity bundles' => [
        "eck.entity.{$this->entityTypeInfo['id']}_type.list",
        "eck.entity.{$this->entityTypeInfo['id']}_type.add",
        "entity.{$this->entityTypeInfo['id']}_type.edit_form",
        "entity.{$this->entityTypeInfo['id']}_type.delete_form",
      ],
    ];
    $routeArguments = [
      "{$this->entityTypeInfo['id']}_type" => $this->bundleInfo['type'],
    ];

    foreach ($routes as $routeNames) {
      foreach ($routeNames as $routeName) {
        $this->drupalGet(Url::fromRoute($routeName, $routeArguments));
        // Anonymous users can not access the route.
        $this->assertSession()->statusCodeEquals(403);
      }
    }

    \Drupal::entityTypeManager()->clearCachedDefinitions();
    foreach ($routes as $permission => $routeNames) {
      $this->drupalLogin($this->drupalCreateUser([$permission]));
      foreach ($routeNames as $routeName) {
        $this->drupalGet(Url::fromRoute($routeName, $routeArguments));
        // Users with the correct permission can access the route.
        $this->assertSession()->statusCodeEquals(200);
      }
    }
  }

  /**
   * Tests if access handling for created entities is handled correctly.
   */
  public function testEntityAccess() {
    $entityTypeName = $this->entityTypeInfo['id'];
    $ownEntityPermissions = $anyEntityPermissions = ["create {$entityTypeName} entities"];
    foreach (['view', 'edit', 'delete'] as $op) {
      $ownEntityPermissions[] = "{$op} own {$entityTypeName} entities";
      $anyEntityPermissions[] = "{$op} any {$entityTypeName} entities";
    }
    $ownEntityUser = $this->drupalCreateUser($ownEntityPermissions);
    $anyEntityUser = $this->drupalCreateUser($anyEntityPermissions);

    $this->drupalLogin($anyEntityUser);
    $edit['title[0][value]'] = $this->randomMachineName();
    $route_args = [
      'eck_entity_type' => $entityTypeName,
      'eck_entity_bundle' => $this->bundleInfo['type'],
    ];
    $this->drupalGet(Url::fromRoute("eck.entity.add", $route_args));
    $this->submitForm($edit, 'Save');

    $this->drupalLogin($ownEntityUser);
    $edit['title[0][value]'] = $this->randomMachineName();
    $route_args = [
      'eck_entity_type' => $entityTypeName,
      'eck_entity_bundle' => $this->bundleInfo['type'],
    ];
    $this->drupalGet(Url::fromRoute("eck.entity.add", $route_args));
    $this->submitForm($edit, 'Save');

    // Get the entity that was created by the 'any' user.
    $arguments = [$entityTypeName => 1];
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    // The 'own' user has no permission to see content which is not theirs.
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.edit_form", $arguments));
    // The 'own' user has no permission to edit content which is not theirs.
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.delete_form", $arguments));
    // The 'own' user has no permission to delete content which is not theirs.
    $this->assertSession()->statusCodeEquals(403);
    // Get the entity that was created by the 'own' user.
    $arguments = [$entityTypeName => 2];
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    // The 'own' user has permission to see their own content.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.edit_form", $arguments));
    // The 'own' user has permission to edit their own content.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.delete_form", $arguments));
    // The 'own' user has permission to delete their own content.
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogin($anyEntityUser);
    // Get the entity that was created by the 'any' user.
    $arguments = [$entityTypeName => 1];
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    // The 'any' user has permission to see their own content.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.edit_form", $arguments));
    // The 'any' user has permission to edit their own content.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.delete_form", $arguments));
    // The 'any' user has permission to delete their own content.
    $this->assertSession()->statusCodeEquals(200);
    // Get the entity that was created by the 'own' user.
    $arguments = [$entityTypeName => 2];
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    // The 'any' user has permission to see content which is not theirs.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.edit_form", $arguments));
    // The 'any' user has permission to edit content which is not theirs.
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.delete_form", $arguments));
    // The 'any' user has permission to delete content which is not theirs.
    $this->assertSession()->statusCodeEquals(200);

    // Create entity with "Unpublished" status.
    $this->createEntity($entityTypeName, [
      'type' => $this->bundleInfo['type'],
      'title' => $this->randomString(),
      'status' => FALSE,
    ]);

    // Normal users should not have access to unpublished entities.
    $arguments = [$entityTypeName => 3];
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    $this->assertSession()->statusCodeEquals(403);

    // This one permission should be not enough to get access.
    $viewUnpublishedEntityUser = $this->drupalCreateUser([
      'view unpublished eck entities',
    ]);
    $this->drupalLogin($viewUnpublishedEntityUser);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    $this->assertSession()->statusCodeEquals(200);

    // Finally, users with normal access and 'view unpublished eck entities'
    // permission should have access.
    $viewUnpublishedAndAnyEntityUser = $this->drupalCreateUser([
      'view unpublished eck entities',
      "view any {$entityTypeName} entities",
    ]);
    $this->drupalLogin($viewUnpublishedAndAnyEntityUser);
    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    $this->assertSession()->statusCodeEquals(200);

    // Entities should not be viewable if the standalone_url option is disabled.
    $config = \Drupal::configFactory()->getEditable("eck.eck_entity_type.{$entityTypeName}");
    $config->set('standalone_url', FALSE);
    $config->save();
    \Drupal::service('router.builder')->rebuild();

    $this->drupalGet(Url::fromRoute("entity.{$entityTypeName}.canonical", $arguments));
    $this->assertSession()->statusCodeEquals(403);

  }

}

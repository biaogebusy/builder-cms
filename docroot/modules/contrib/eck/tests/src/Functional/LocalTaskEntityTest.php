<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Tests the local task links in entities.
 *
 * @group eck
 */
class LocalTaskEntityTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'block'];

  /**
   * Information about the entity type we are using for testing.
   *
   * @var array
   *
   * @see \Drupal\Tests\eck\Functional\FunctionalTestBase::createEntityType()
   */
  protected $entityTypeInfo;

  /**
   * Information about the bundle we are using for testing.
   *
   * @var array
   *
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

    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Tests that the entity contains the local task links.
   */
  public function testLocalTask() {
    $edit['title[0][value]'] = $this->randomMachineName();
    $route_args = [
      'eck_entity_type' => $this->entityTypeInfo['id'],
      'eck_entity_bundle' => $this->bundleInfo['type'],
    ];
    $this->drupalGet(Url::fromRoute("eck.entity.add", $route_args));
    $this->submitForm($edit, 'Save');

    $route_args = [
      $this->entityTypeInfo['id'] => 1,
    ];
    $this->assertLocalTasksFor("entity.{$this->entityTypeInfo['id']}.canonical", $route_args);
    $this->assertLocalTasksFor("entity.{$this->entityTypeInfo['id']}.edit_form", $route_args);
    $this->assertLocalTasksFor("entity.{$this->entityTypeInfo['id']}.delete_form", $route_args);
  }

  /**
   * Go to a page and check if exist the local task links.
   *
   * @param string $route
   *   The route.
   * @param array $routeArguments
   *   The rout arguments.
   */
  protected function assertLocalTasksFor($route, array $routeArguments) {
    $this->drupalGet(Url::fromRoute($route, $routeArguments));
    $this->assertLocalTaskLinkRoute("entity.{$this->entityTypeInfo['id']}.canonical", $routeArguments, 'View');
    $this->assertLocalTaskLinkRoute("entity.{$this->entityTypeInfo['id']}.edit_form", $routeArguments, 'Edit');
    $this->assertLocalTaskLinkRoute("entity.{$this->entityTypeInfo['id']}.delete_form", $routeArguments, 'Delete');
  }

  /**
   * Pass if a link with the specified label and href is found.
   *
   * @param string $route
   *   The route name.
   * @param array $route_args
   *   The route arguments.
   * @param string $label
   *   Text between the anchor tags.
   */
  protected function assertLocalTaskLinkRoute($route, array $route_args, $label) {
    $url = Url::fromRoute($route, $route_args);
    $links = $this->xpath('//ul/li/a[contains(@href, :href) and normalize-space(text())=:label]', [
      ':href' => $url->toString(),
      ':label' => $label,
    ]);

    $this->assertEquals(1, \count($links), $this->t('Link with label %label found and its route is :route', [
      ':route' => $route,
      '%label' => $label,
    ]));
  }

}

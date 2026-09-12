<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\eck\Entity\EckEntityType;

/**
 * Defines functional tests for Workspaces integration.
 *
 * @group eck
 */
class WorkspacesIntegrationTest extends FunctionalTestBase {

  /**
   * Tests that workspaces can be enabled when no entity types are defined.
   *
   * @test
   */
  public function workspacesCanBeEnabledWhenNoEntityTypesAreDefined() {
    $this->container->get('module_installer')->install(['workspaces'], TRUE);
  }

  /**
   * Tests that workspaces can be enabled when an entity type is defined.
   *
   * @test
   */
  public function workspacesCanBeEnabledWhenEntityTypeIsDefined() {
    $testType = EckEntityType::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $testType->save();

    $this->container->get('module_installer')->install(['workspaces'], TRUE);
  }

  /**
   * Tests that cache can be cleared when Workbench is enabled.
   *
   * @test
   */
  public function cacheCanBeClearedWhenWorkbenchIsEnabled() {
    $testType = EckEntityType::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $testType->save();

    $this->container->get('module_installer')->install(['workspaces'], TRUE);

    drupal_flush_all_caches();
  }

  /**
   * Tests that new entity types can be created when Workbench is enabled.
   *
   * @test
   */
  public function newEntityTypesCanBeCreatedWhenWorkbenchIsEnabled() {
    $this->assertEquals(0, \count(EckEntityType::loadMultiple()));
    $this->container->get('module_installer')->install(['workspaces'], TRUE);

    $testType = EckEntityType::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $testType->save();

    $this->assertEquals(1, \count(EckEntityType::loadMultiple()));
  }

}

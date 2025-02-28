<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\eck\Entity\EckEntityType;
use Drupal\Tests\BrowserTestBase;

/**
 * Class WorkspacesIntegrationTest.
 *
 * @group eck
 */
class WorkspacesIntegrationTest extends BrowserTestBase {

  protected static $modules = ['eck'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * @test
   */
  public function workspacesCanBeEnabledWhenNoEntityTypesAreDefined() {
    $this->container->get('module_installer')->install(['workspaces'], TRUE);
  }

  /**
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
   * @test
   */
  public function newEntityTypesCanBeCreatedWhenWorkbenchIsEnabled() {
    $this->assertEquals(0, count(EckEntityType::loadMultiple()));
    $this->container->get('module_installer')->install(['workspaces'], TRUE);

    $testType = EckEntityType::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $testType->save();

    $this->assertEquals(1, count(EckEntityType::loadMultiple()));
  }

}

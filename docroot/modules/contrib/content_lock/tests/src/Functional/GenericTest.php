<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for Content Lock.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {

  /**
   * {@inheritdoc}
   */
  protected function assertHookHelp(string $module): void {
    // Overrides method because we have no online documentation.
    $info = \Drupal::service('extension.list.module')->getExtensionInfo($module);
    $this->drupalGet('admin/help/' . $module);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($info['name'] . ' module');
  }

}

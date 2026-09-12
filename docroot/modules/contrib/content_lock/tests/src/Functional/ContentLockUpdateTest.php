<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Update tests.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    if (file_exists(DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz')) {
      $this->databaseDumpFiles = [
        DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      ];
    }
    else {
      $this->databaseDumpFiles = [
        DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
      ];
    }
    $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/install-content-lock.php';

    if ($this->name() === 'testContentLockTimeout') {
      $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/install-content-lock-timeout.php';
    }
  }

  /**
   * Tests updating Content Lock when Content Lock Timeout is not installed.
   */
  public function testContentLock() {
    $config = $this->config('content_lock.settings')->get();
    $this->assertSame(1, $config['verbose']);
    $this->assertArrayNotHasKey('timeout', $config);
    $this->assertArrayHasKey('node', $config['types']);
    $this->assertArrayHasKey('taxonomy_term', $config['types']);
    $this->assertArrayHasKey('node', $config['form_op_lock']);
    $this->assertArrayHasKey('taxonomy_term', $config['form_op_lock']);
    $this->assertSame(['node', 'taxonomy_term'], $config['types_translation_lock']);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module not installed');
    $this->assertFalse($this->config('system.action.node_break_lock_action')->isNew());
    $this->assertFalse($this->config('system.action.taxonomy_term_break_lock_action')->isNew());

    $this->runUpdates();
    $config = $this->config('content_lock.settings')->get();
    $this->assertTrue($config['verbose']);
    $this->assertNull($config['timeout']);
    $this->assertArrayHasKey('node', $config['types']);
    $this->assertArrayNotHasKey('taxonomy_term', $config['types']);
    $this->assertArrayHasKey('node', $config['form_op_lock']);
    $this->assertArrayNotHasKey('taxonomy_term', $config['form_op_lock']);
    $this->assertSame(['node'], $config['types_translation_lock']);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module not installed');
    $this->assertFalse($this->config('system.action.node_break_lock_action')->isNew());
    $this->assertTrue($this->config('system.action.taxonomy_term_break_lock_action')->isNew());
  }

  /**
   * Tests updating Content Lock when Content Lock Timeout is installed.
   */
  public function testContentLockTimeout() {
    $config = $this->config('content_lock.settings')->get();
    $this->assertArrayNotHasKey('timeout', $config);
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module installed');

    $this->runUpdates();
    $config = $this->config('content_lock.settings')->get();
    $this->assertSame(1800, $config['timeout']);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module not installed');
  }

}

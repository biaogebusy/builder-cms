<?php

namespace Drupal\Tests\allowed_formats\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * @group allowed_formats
 * @group legacy
 */
class Formats2CoreUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.filled.standard.php.gz',
      __DIR__ . '/../../fixtures/update/allowed_formats_post_update_formats2core.php',
    ];
  }

  /**
   * @covers \allowed_formats_post_update_formats2core
   */
  public function testFormats2Core(): void {
    $config = $this->config('field.field.node.article.body');
    // Check that, before update, allowed formats exist as 3rd-party settings.
    $this->assertSame(['full_html', 'restricted_html'], $config->get('third_party_settings.allowed_formats.allowed_formats'));
    // Check that, before update, allowed formats exist as field settings.
    $this->assertSame(['basic_html'], $config->get('settings.allowed_formats'));

    $this->runUpdates();

    $config = $this->config('field.field.node.article.body');
    // Check that, after update, allowed_formats 3rd-party settings are removed.
    $this->assertArrayNotHasKey('allowed_formats', $config->get('third_party_settings') ?? []);
    // Check that, after update, allowed formats were set from module settings.
    $this->assertSame(['full_html', 'restricted_html'], $config->get('settings.allowed_formats'));
  }

}

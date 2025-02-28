<?php

namespace Drupal\Tests\lightning_core\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lightning_core\UpdateManager;

/**
 * @group lightning_core
 */
class Update8006Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    if (str_starts_with(\Drupal::VERSION, '10.')) {
      $dump_file = 'drupal-9.4.0.bare.standard.php.gz';
    }
    else {
      $dump_file = 'drupal-8.8.0.bare.standard.php.gz';
    }
    $this->databaseDumpFiles = [
      $this->getDrupalRoot() . "/core/modules/system/tests/fixtures/update/$dump_file",
      __DIR__ . '/../../../fixtures/Update8006Test.php.gz',
    ];
  }

  public function testUpdate() {
    // Forcibly remove Lightning Dev to prevent test failures. This does not
    // affect the test, because Lightning Dev should never have been included in
    // the fixture anyway.
    $this->config('core.extension')
      ->clear('module.lightning_dev')
      ->save();
    $this->container->get('keyvalue')
      ->get('system.schema')
      ->deleteMultiple(['lightning_dev']);

    $old_name = 'lightning.versions';
    $new_name = UpdateManager::CONFIG_NAME;

    // When doing a lot of updates, it's possible that lightning_core.versions
    // will exist before update 8007 runs, so we need to simulate that case.
    $this->config($new_name)->set('foo', '5.1.0')->save();

    // Ensure that update 8006 works if lightning.versions exists.
    $this->config($old_name)
      ->set('foo', '5.0.0')
      ->set('bar', '1.0.0')
      ->save();

    $this->runUpdates();

    // Assert that lightning.versions is gone, but its data is preserved.
    $this->assertTrue($this->config($old_name)->isNew());
    $new = $this->config($new_name);
    $this->assertSame('5.1.0', $new->get('foo'));
    $this->assertSame('1.0.0', $new->get('bar'));
  }

}

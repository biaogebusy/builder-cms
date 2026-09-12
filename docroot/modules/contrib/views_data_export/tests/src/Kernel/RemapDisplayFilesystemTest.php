<?php

declare(strict_types=1);

namespace Drupal\Tests\views_data_export\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\ViewEntityInterface;
use Drupal\views\Entity\View;

/**
 * Test the post update function that remaps the filesystem display option.
 *
 * @group views_data_export
 */
final class RemapDisplayFilesystemTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'rest',
    'serialization',
    'system',
    'user',
    'views',
    'views_data_export',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['views']);

    $modulePath = $this->container->get('extension.list.module')->getPath('views_data_export');
    require_once DRUPAL_ROOT . '/' . $modulePath . '/views_data_export.post_update.php';
  }

  /**
   * Create a test view.
   *
   * @param string $id
   *   ID of the view. This is reused for the label.
   * @param array $options
   *   Display options for the data export display in this view.
   *
   * @return \Drupal\views\ViewEntityInterface
   *   Test view.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTestView(string $id, array $options): ViewEntityInterface {
    $config = [
      'id' => $id,
      'label' => $id,
      'uuid' => $this->container->get('uuid')->generate(),
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [],
        ],
        'export_1' => [
          'display_plugin' => 'data_export',
          'id' => 'export_1',
          'display_title' => 'Export',
          'display_options' => $options,
        ],
      ],
    ];

    // Bypass schema validation by writing directly to active config.
    $this->container->get('config.storage')->write("views.view.$id", $config);

    return View::load($id);
  }

  /**
   * Test display using public filesystem correctly mapped.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPublicMapping(): void {
    $this->createTestView('publicly_stored_data_export', [
      'store_in_public_file_directory' => TRUE,
    ]);

    $sandbox = [];
    views_data_export_post_update_remap_display_filesystem_redux($sandbox);

    $options = View::load('publicly_stored_data_export')->getDisplay('export_1')['display_options'];

    $this->assertEquals('public', $options['export_filesystem']);
    $this->assertArrayNotHasKey('store_in_public_file_directory', $options);
  }

  /**
   * Test display using private filesystem correctly mapped.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPrivateMapping(): void {
    $this->createTestView('privately_stored_data_export', [
      'store_in_public_file_directory' => FALSE,
    ]);

    $sandbox = [];
    views_data_export_post_update_remap_display_filesystem_redux($sandbox);

    $options = View::load('privately_stored_data_export')->getDisplay('export_1')['display_options'];

    $this->assertEquals('private', $options['export_filesystem']);
    $this->assertArrayNotHasKey('store_in_public_file_directory', $options);
  }

  /**
   * Test display using no explicit filesystem mapped to public.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNullMapping(): void {
    $this->createTestView('null_stored_data_export', [
      'store_in_public_file_directory' => NULL,
    ]);

    $sandbox = [];
    views_data_export_post_update_remap_display_filesystem_redux($sandbox);

    $options = View::load('null_stored_data_export')->getDisplay('export_1')['display_options'];

    $this->assertEquals('public', $options['export_filesystem']);
    $this->assertArrayNotHasKey('store_in_public_file_directory', $options);
  }

  /**
   * Test display with no old key, with and without private filesystem.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testNoOldKeyWithAndWithoutPrivateFilesystemAvailable(): void {
    // First test without private:// available.
    $this->createTestView('no_stored_data_export_without_private_filesystem', []);

    $sandbox = [];
    views_data_export_post_update_remap_display_filesystem_redux($sandbox);

    $options = View::load('no_stored_data_export_without_private_filesystem')->getDisplay('export_1')['display_options'];
    $this->assertEquals('public', $options['export_filesystem']);
    $this->assertArrayNotHasKey('store_in_public_file_directory', $options);

    // Now test with private:// available.
    $this->createTestView('no_stored_data_export_with_private_filesystem', []);

    // Ensure private:// exists.
    $private_path = $this->container->getParameter('site.path') . '/private';
    $this->container->get('file_system')->mkdir($private_path, NULL, TRUE);
    $this->setSetting('file_private_path', $private_path);
    $this->container->get('kernel')->rebuildContainer();

    $sandbox = [];
    views_data_export_post_update_remap_display_filesystem_redux($sandbox);

    $options = View::load('no_stored_data_export_with_private_filesystem')->getDisplay('export_1')['display_options'];

    $this->assertEquals('private', $options['export_filesystem']);
    $this->assertArrayNotHasKey('store_in_public_file_directory', $options);
  }

}

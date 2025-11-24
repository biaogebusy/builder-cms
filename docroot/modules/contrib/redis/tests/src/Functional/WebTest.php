<?php

namespace Drupal\Tests\redis\Functional;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Core\Database\Database;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\cron_queue_test\Plugin\QueueWorker\CronQueueTestException;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests complex processes like installing modules with redis backends.
 *
 * @group redis
 */
class WebTest extends BrowserTestBase {

  use FieldUiTestTrait;
  use RedisTestInterfaceTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['redis', 'block', 'cron_queue_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');

    // Set in-memory settings.
    $settings = Settings::getAll();

    // Get REDIS_INTERFACE env variable.
    $redis_interface = self::getRedisInterfaceEnv();
    $settings['redis.connection']['interface'] = $redis_interface;

    if ($host = getenv('REDIS_HOST')) {
      $settings['redis.connection']['host'] = $host;
    }

    $settings['redis_compress_length'] = 100;

    $settings['cache'] = [
      'default' => 'cache.backend.redis',
    ];

    $settings['queue_default'] = 'queue.redis';


    $settings['container_yamls'][] = \Drupal::service('extension.list.module')->getPath('redis') . '/example.services.yml';

    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'redis.factory' => [
          'class' => 'Drupal\redis\ClientFactory',
        ],
        'cache.backend.redis' => [
          'class' => 'Drupal\redis\Cache\CacheBackendFactory',
          'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
        ],
        'cache.container' => [
          'class' => '\Drupal\redis\Cache\PhpRedis',
          'factory' => ['@cache.backend.redis', 'get'],
          'arguments' => ['container'],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
          'arguments' => ['@redis.factory'],
        ],
        'serialization.phpserialize' => [
          'class' => 'Drupal\Component\Serialization\PhpSerialize',
        ],
      ],
    ];
    new Settings($settings);

    // Write the containers_yaml update by hand, since writeSettings() doesn't
    // support some of the definitions.
    $filename = $this->siteDirectory . '/settings.php';
    chmod($filename, 0666);
    $contents = file_get_contents($filename);

    // Add the container_yaml and cache definition.
    $contents .= "\n\n" . '$settings["container_yamls"][] = "' . \Drupal::service('extension.list.module')->getPath('redis') . '/example.services.yml";';
    $contents .= "\n\n" . '$settings["cache"] = ' . var_export($settings['cache'], TRUE) . ';';
    $contents .= "\n\n" . '$settings["redis_compress_length"] = 100;';
    $contents .= "\n\n" . '$settings["redis.connection"]["interface"] = "' . $redis_interface . '";';

    if ($host = getenv('REDIS_HOST')) {
      $contents .= "\n\n" . '$settings["redis.connection"]["host"] = "' . $host . '";';
    }

    $contents .= "\n\n" . '$settings["queue_default"] = "queue.redis";';

    // Add the classloader.
    $contents .= "\n\n" . '$class_loader->addPsr4(\'Drupal\\\\redis\\\\\', \'' . \Drupal::service('extension.list.module')->getPath('redis') . '/src\');';

    // Add the bootstrap container definition.
    $contents .= "\n\n" . '$settings["bootstrap_container_definition"] = ' . var_export($settings['bootstrap_container_definition'], TRUE) . ';';

    file_put_contents($filename, $contents);
    OpCodeCache::invalidate(DRUPAL_ROOT . '/' . $filename);

    // Reset the cache factory.
    $this->rebuildContainer();

    // Get database schema.
    $db_schema = Database::getConnection()->schema();

    // Make sure that the cache and lock tables aren't used.
    $db_schema->dropTable('cache_default');
    $db_schema->dropTable('cache_render');
    $db_schema->dropTable('cache_config');
    $db_schema->dropTable('cache_container');
    $db_schema->dropTable('cachetags');
    $db_schema->dropTable('semaphore');
    $db_schema->dropTable('flood');
    $db_schema->dropTable('queue');
  }

  /**
   * Tests enabling modules and creating configuration.
   */
  public function testModuleInstallation() {
    $admin_user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin_user);

    // Enable a few modules.
    $edit["modules[node][enable]"] = TRUE;
    $edit["modules[views][enable]"] = TRUE;
    $edit["modules[field_ui][enable]"] = TRUE;
    $edit["modules[text][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');
    $this->submitForm([], 'Continue');

    $assert = $this->assertSession();

    // The order of the modules is not guaranteed, so just assert that they are
    // all listed.
    $assert->elementTextContains('css', '.messages--status', '6 modules have been');
    $assert->elementTextContains('css', '.messages--status', 'Field UI');
    $assert->elementTextContains('css', '.messages--status', 'Node');
    $assert->elementTextContains('css', '.messages--status', 'Text');
    $assert->elementTextContains('css', '.messages--status', 'Views');
    $assert->elementTextContains('css', '.messages--status', 'Field');
    $assert->elementTextContains('css', '.messages--status', 'Filter');
    $assert->checkboxChecked('edit-modules-field-ui-enable');

    \Drupal::service(FieldTypePluginManagerInterface::class)->clearCachedDefinitions();

    // Create a node type with a field.
    $edit = [
      'name' => $this->randomString(),
      'options[promote]' => '1',
      'type' => $node_type = mb_strtolower($this->randomMachineName()),
    ];
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm($edit, 'Save and manage fields');
    $field_name = mb_strtolower($this->randomMachineName());
    $this->fieldUIAddNewField('admin/structure/types/manage/' . $node_type, $field_name, NULL, 'text');

    // Create a node, check display, edit, verify that it has been updated.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_' . $field_name . '[0][value]' => $this->randomMachineName(),
    ];
    $this->drupalGet('node/add/' . $node_type);
    $this->submitForm($edit, 'Save');

    // Test the output as anonymous user.
    $this->drupalLogout();
    $this->drupalGet('node');
    $this->assertSession()->responseContains($edit['title[0][value]']);

    $this->drupalLogin($admin_user);
    $this->drupalGet('node');
    $this->clickLink($edit['title[0][value]']);
    $this->clickLink(t('Edit'));
    $update = [
      'title[0][value]' => $this->randomMachineName(),
    ];
    $this->submitForm($update, 'Save');
    $this->assertSession()->responseContains($update['title[0][value]']);
    $this->drupalGet('node');
    $this->assertSession()->responseContains($update['title[0][value]']);

    $this->drupalLogout();
    $this->drupalGet('node');
    $this->clickLink($update['title[0][value]']);

    // Manually add a queue item and process it, to test the queue factory.
    // Get the queue to test the normal Exception.
    $queue = \Drupal::queue(CronQueueTestException::PLUGIN_ID);

    // Enqueue an item for processing.
    $queue->createItem([$this->randomMachineName() => $this->randomMachineName()]);

    // Run cron; the worker for this queue should throw an exception and handle
    // it.
    \Drupal::service('cron')->run();
    $this->assertEquals(1, \Drupal::state()->get('cron_queue_test_exception'));

    // Access the reports page.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/reports/redis');
    $this->assertSession()->pageTextContains('Connected, using the ' . self::getRedisInterfaceEnv() . ' client');
    $this->assertSession()->pageTextMatches('/config: [0-9]*[1-9][0-9]*/');

    $this->assertSession()->pageTextMatches('/.+ \/ .+ \([0-9]{1,2}%\), maxmemory policy: .*/');

    // Assert a few cache bins for a non-zero item count.
    $this->assertSession()->pageTextMatches('/data: [0-9]*[1-9][0-9]*/');
    $this->assertSession()->pageTextMatches('/discovery: [0-9]*[1-9][0-9]*/');
    $this->assertSession()->pageTextMatches('/dynamic_page_cache: [0-9]*[1-9][0-9]*/');

    // Assert render cache entries.
    $this->assertSession()->pageTextMatches('/entity_view:block:.+: [0-9]*[1-9][0-9]* \(.+\)/');
    $this->assertSession()->pageTextMatches('/view:frontpage:display:page_1: [0-9]*[1-9][0-9]* \(.+\)/');

    // Assert a few cache tags for a non-zero invalidation count.
    $this->assertSession()->pageTextContains('Most invalidated cache tags');
    $this->assertSession()->pageTextMatches('/entity_types: [0-9]*[1-9][0-9]*/');
    $this->assertSession()->pageTextMatches('/entity_field_info: [0-9]*[1-9][0-9]*/');
    $this->assertSession()->pageTextMatches('/[0-9]*[1-9][0-9]* tags with [0-9]*[1-9][0-9]* invalidations/');

    if (static::getRedisInterfaceEnv() == 'Relay') {
      $this->assertSession()->pageTextMatches('/[0-9.]+ ([KM])B \/ \d{2} MB memory usage, eviction policy: .+/');
    }

    // Get database schema.
    $db_schema = Database::getConnection()->schema();
    $this->assertFalse($db_schema->tableExists('cache_default'));
    $this->assertFalse($db_schema->tableExists('cache_render'));
    $this->assertFalse($db_schema->tableExists('cache_config'));
    $this->assertFalse($db_schema->tableExists('cache_container'));
    $this->assertFalse($db_schema->tableExists('cachetags'));
    $this->assertFalse($db_schema->tableExists('semaphore'));
    $this->assertFalse($db_schema->tableExists('flood'));
    $this->assertFalse($db_schema->tableExists('queue'));
  }

}

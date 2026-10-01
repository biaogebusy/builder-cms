<?php

namespace Drupal\Tests\xinshi_editor\Unit;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Development\ConfigSchemaChecker;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Validation\ConstraintManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Yaml\Yaml;

/** Checks existing editor shapes and block revisions through core config saves. */
final class EditorConfigSchemaTest extends TestCase {

  private MemoryStorage $storage;
  private ConfigFactory $factory;
  private string $root;
  private ?array $fileCacheConfiguration;

  protected function setUp(): void {
    parent::setUp();
    $this->fileCacheConfiguration = FileCacheFactory::getConfiguration();
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    $modulePath = dirname(__DIR__, 3);
    $this->root = dirname($modulePath, 4);
    $this->storage = new MemoryStorage();
    $schema = new MemoryStorage();
    $files = [$this->root . '/docroot/core/config/schema/core.data_types.schema.yml'];
    foreach (['editor', 'block', 'block_content', 'text'] as $module) {
      $files = array_merge($files, glob($this->root . '/docroot/core/modules/' . $module . '/config/schema/*.yml'));
    }
    $files = array_merge($files, glob($modulePath . '/config/schema/*.yml'));
    foreach ($files as $file) {
      $schema->write(basename($file, '.yml'), Yaml::parseFile($file));
    }
    $container = new ContainerBuilder();
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('alter')->willReturnCallback(static function ($type, &$definitions): void {
      if ($type === 'config_schema_info' && function_exists('xinshi_editor_config_schema_info_alter')) {
        xinshi_editor_config_schema_info_alter($definitions);
      }
    });
    $cache = new NullBackend('config');
    $typed = new TypedConfigManager($this->storage, $schema, $cache, $modules, new ClassResolver($container));
    $typed->setValidationConstraintManager(new ConstraintManager(new \ArrayObject([
      'Drupal\\Core\\Validation' => $this->root . '/docroot/core/lib/Drupal/Core/Validation',
      'Drupal\\Core\\Config' => $this->root . '/docroot/core/lib/Drupal/Core/Config',
    ]), $cache, $modules));
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new ConfigSchemaChecker($typed));
    $this->factory = new ConfigFactory($this->storage, $dispatcher, $typed);
    $dispatcher->addSubscriber($this->factory);
    $container->set('config.typed', $typed);
    $container->set('config.factory', $this->factory);
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    FileCacheFactory::setConfiguration($this->fileCacheConfiguration);
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public static function settingsLayouts(): array {
    $flat = ['height' => '75vh', 'mode' => 'tree', 'allow_modes' => ['code' => 'code', 'view' => 0]];
    return [[$flat], [['fieldset' => $flat]], [$flat + ['fieldset' => $flat]]];
  }

  #[DataProvider('settingsLayouts')]
  public function testEditorSettingsKeepLegacyAndFormLayouts(array $settings): void {
    $name = 'editor.editor.site_json';
    $data = ['format' => 'site_json', 'editor' => 'json_editor', 'settings' => $settings, 'image_upload' => ['status' => FALSE]];
    $this->factory->getEditable($name)->setData($data)->save();
    $saved = $this->storage->read($name);
    $this->assertEquals($data, $saved);
    foreach ([$saved['settings'], $saved['settings']['fieldset'] ?? []] as $options) {
      if (isset($options['allow_modes'])) {
        $this->assertSame('0', $options['allow_modes']['view']);
        $this->assertSame(['code'], array_keys(array_filter($options['allow_modes'])));
      }
    }
    $this->factory->reset($name);
    $this->factory->getEditable($name)->save();
    $this->assertSame($saved, $this->storage->read($name));
  }

  public function testBlockPlacementKeepsRevisionAndCoreSettings(): void {
    $name = 'block.block.site_component';
    $plugin = 'block_content:ded71d1d-d872-446a-bc43-171c69703fe2';
    $data = [
      'id' => 'site_component', 'plugin' => $plugin,
      'settings' => [
        'id' => $plugin, 'label' => 'Site component', 'label_display' => '0',
        'provider' => 'block_content', 'view_mode' => 'full',
        'context_mapping' => [], 'vid' => 42,
      ],
    ];
    $this->factory->getEditable($name)->setData($data)->save();
    $this->assertEquals($data, $this->storage->read($name));
    $this->assertSame(42, $this->storage->read($name)['settings']['vid']);
  }

  public function testSchemaAlterPreservesCoreAndOtherExtensions(): void {
    $definitions = Yaml::parseFile($this->root . '/docroot/core/modules/block_content/config/schema/block_content.schema.yml');
    $definitions['block.settings.block_content:*']['mapping']['site_option'] = ['type' => 'string'];
    $original = $definitions;
    xinshi_editor_config_schema_info_alter($definitions);
    $this->assertSame('integer', $definitions['block.settings.block_content:*']['mapping']['vid']['type']);
    unset($definitions['block.settings.block_content:*']['mapping']['vid']);
    $this->assertSame($original, $definitions);
  }

  public function testMissingOptionalBlockModuleDoesNotCreateSchemaOrPlugins(): void {
    $definitions = ['site.settings' => ['type' => 'config_object']];
    xinshi_editor_config_schema_info_alter($definitions);
    $this->assertSame(['site.settings' => ['type' => 'config_object']], $definitions);
    $plugins = ['site_block' => ['class' => 'SiteBlock']];
    xinshi_editor_block_alter($plugins);
    $this->assertSame(['site_block' => ['class' => 'SiteBlock']], $plugins);
  }

  public function testExistingRevisionSchemaIsNotOverwritten(): void {
    $definitions = [
      'block.settings.block_content:*' => [
        'type' => 'block_settings',
        'mapping' => ['vid' => [
          'type' => 'integer', 'label' => 'Existing revision definition',
          'requiredKey' => FALSE, 'constraints' => ['Range' => ['min' => 1]],
        ]],
      ],
    ];
    $original = $definitions;
    xinshi_editor_config_schema_info_alter($definitions);
    $this->assertSame($original, $definitions);
  }

}

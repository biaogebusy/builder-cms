<?php

namespace Drupal\Tests\xinshi_legacy\Unit;

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
use Drupal\xinshi_legacy\Plugin\Field\FieldWidget\MediaLibraryWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Yaml\Yaml;

/** Checks legacy widget settings through core form-display config saves. */
final class LegacyWidgetConfigSchemaTest extends TestCase {

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
    $files = glob($this->root . '/docroot/core/config/schema/*.yml');
    foreach (['media_library'] as $module) {
      $files = array_merge($files, glob($this->root . '/docroot/core/modules/' . $module . '/config/schema/*.yml'));
    }
    $files = array_merge($files, glob($modulePath . '/config/schema/*.yml'));
    foreach ($files as $file) {
      $schema->write(basename($file, '.yml'), Yaml::parseFile($file));
    }
    $container = new ContainerBuilder();
    $modules = $this->createMock(ModuleHandlerInterface::class);
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

  public static function widgetSettings(): array {
    $defaults = MediaLibraryWidget::defaultSettings();
    return [
      'media defaults' => ['xinshi_media_library_widget', $defaults],
      'existing customized media' => ['xinshi_media_library_widget', [
        'title' => 'Site media', 'width' => '920px', 'height' => '80vh',
        'description' => 1, 'border' => '1', 'hidden_title' => TRUE,
        'view' => 'site_media', 'display_id' => 'picker', 'widget_grid' => '6',
        'edit' => '0', 'media_types' => ['image', 'document'],
      ]],
      'empty moderation settings' => ['xinshi_moderation_state_button', []],
    ];
  }

  #[DataProvider('widgetSettings')]
  public function testFormDisplaySettingsRoundTrip(string $plugin, array $settings): void {
    $name = 'core.entity_form_display.node.page.default';
    $data = [
      'id' => 'node.page.default', 'targetEntityType' => 'node',
      'bundle' => 'page', 'mode' => 'default', 'status' => TRUE,
      'content' => ['field_example' => [
        'type' => $plugin, 'weight' => 2, 'region' => 'content',
        'settings' => $settings, 'third_party_settings' => [],
      ]],
      'hidden' => [],
    ];
    $this->factory->getEditable($name)->setData($data)->save();
    $saved = $this->storage->read($name);
    $this->assertEquals($data, $saved);
    if ($plugin === 'xinshi_media_library_widget') {
      $actual = $saved['content']['field_example']['settings'];
      $this->assertSame($settings['media_types'], $actual['media_types']);
      foreach (['description', 'border', 'hidden_title', 'edit'] as $key) {
        $this->assertSame((bool) $settings[$key], $actual[$key]);
      }
    }
    $this->factory->reset($name);
    $this->factory->getEditable($name)->save();
    $this->assertSame($saved, $this->storage->read($name));
  }

}

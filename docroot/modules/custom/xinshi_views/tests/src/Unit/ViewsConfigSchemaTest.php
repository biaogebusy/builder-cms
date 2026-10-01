<?php

namespace Drupal\Tests\xinshi_views\Unit;

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

/** Checks plugin-specific settings through the containing Views config schema. */
final class ViewsConfigSchemaTest extends TestCase {

  private MemoryStorage $storage;
  private ConfigFactory $factory;
  private ?array $fileCacheConfiguration;

  protected function setUp(): void {
    parent::setUp();
    $this->fileCacheConfiguration = FileCacheFactory::getConfiguration();
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    $modulePath = dirname(__DIR__, 3);
    $root = dirname($modulePath, 4);
    $this->storage = new MemoryStorage();
    $schema = new MemoryStorage();
    $files = [$root . '/docroot/core/config/schema/core.data_types.schema.yml'];
    foreach (['views', 'rest'] as $module) {
      $files = array_merge($files, glob($root . '/docroot/core/modules/' . $module . '/config/schema/*.yml'));
    }
    $files[] = $modulePath . '/config/schema/xinshi_views.schema.yml';
    foreach ($files as $file) {
      $schema->write(basename($file, '.yml'), Yaml::parseFile($file));
    }
    $container = new ContainerBuilder();
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $cache = new NullBackend('config');
    $typed = new TypedConfigManager($this->storage, $schema, $cache, $modules, new ClassResolver($container));
    $typed->setValidationConstraintManager(new ConstraintManager(new \ArrayObject([
      'Drupal\\Core\\Validation' => $root . '/docroot/core/lib/Drupal/Core/Validation',
      'Drupal\\Core\\Config' => $root . '/docroot/core/lib/Drupal/Core/Config',
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

  public static function plugins(): array {
    return [
      ['rest_export_first_row', 'serializer'],
      ['rest_export_inner_nested', 'serializer'],
      ['rest_export', 'xinshi_pager_serializer'],
      ['rest_export_inner_nested', 'xinshi_pager_serializer'],
    ];
  }

  #[DataProvider('plugins')]
  public function testViewKeepsRouteAuthenticationFormatsAndPager(string $display, string $style): void {
    $data = [
      'id' => 'schema_test',
      'label' => 'Site export',
      'status' => FALSE,
      'display' => [
        'site_export' => [
          'id' => 'site_export',
          'display_title' => 'Custom export',
          'display_plugin' => $display,
          'position' => 1,
          'display_options' => [
            'path' => 'api/site-export',
            'auth' => ['oauth2', 'cookie'],
            'style' => ['type' => $style, 'options' => ['formats' => ['json']]],
            'pager' => ['type' => 'some', 'options' => ['items_per_page' => 7, 'offset' => 3]],
          ],
        ],
      ],
    ];
    $name = 'views.view.schema_test';
    $this->factory->getEditable($name)->setData($data)->save();
    $this->assertEquals($data, $this->storage->read($name));
    // A subsequent save must neither change plugin IDs nor reset site choices.
    $this->factory->reset($name);
    $this->factory->getEditable($name)->save();
    $this->assertEquals($data, $this->storage->read($name));
  }

}

<?php

namespace Drupal\Tests\xinshi_api\Unit;

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
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Validation\ConstraintManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Yaml\Yaml;

/** Verifies defaults and upgrades using Drupal's real typed config storage. */
final class ApiSettingsUpdateTest extends TestCase {

  private const CONFIG = 'xinshi_api.settings';
  private MemoryStorage $storage;
  private ConfigFactory $factory;
  private EventDispatcher $dispatcher;
  private ConfigSchemaChecker $checker;
  private string $modulePath;
  private ?array $fileCacheConfiguration;

  protected function setUp(): void {
    parent::setUp();
    $this->fileCacheConfiguration = FileCacheFactory::getConfiguration();
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    $this->modulePath = dirname(__DIR__, 3);
    $root = dirname($this->modulePath, 4);
    $this->storage = new MemoryStorage();
    $schema = new MemoryStorage();
    foreach ([
      $root . '/docroot/core/config/schema/core.data_types.schema.yml',
      $this->modulePath . '/config/schema/xinshi_api.schema.yml',
    ] as $file) {
      if (is_file($file)) {
        $schema->write(basename($file, '.yml'), Yaml::parseFile($file));
      }
    }
    $container = new ContainerBuilder();
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $cache = new NullBackend('config');
    $typed = new TypedConfigManager($this->storage, $schema, $cache, $modules, new ClassResolver($container));
    $namespaces = new \ArrayObject([
      'Drupal\\Core\\Validation' => $root . '/docroot/core/lib/Drupal/Core/Validation',
      'Drupal\\Core\\Config' => $root . '/docroot/core/lib/Drupal/Core/Config',
    ]);
    $typed->setValidationConstraintManager(new ConstraintManager($namespaces, $cache, $modules));
    $this->dispatcher = new EventDispatcher();
    $this->checker = new ConfigSchemaChecker($typed);
    $this->dispatcher->addSubscriber($this->checker);
    $this->factory = new ConfigFactory($this->storage, $this->dispatcher, $typed);
    $this->dispatcher->addSubscriber($this->factory);
    $container->set('config.typed', $typed);
    $container->set('config.factory', $this->factory);
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    unset($GLOBALS['config'][self::CONFIG]);
    FileCacheFactory::setConfiguration($this->fileCacheConfiguration);
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testInstallDefaultsHaveCompleteSchema(): void {
    $file = $this->modulePath . '/config/install/xinshi_api.settings.yml';
    $this->assertFileExists($file);
    $defaults = Yaml::parseFile($file);
    $this->factory->getEditable(self::CONFIG)->setData($defaults)->save();
    $this->assertSame($defaults, $this->storage->read(self::CONFIG));
    $this->assertFalse($defaults['cache_enable']);
    $this->assertFalse($defaults['debug']);
    $this->assertSame(['not_found' => '', 'access_denied' => ''], $defaults['page']);
  }

  public function testUpgradeCreatesMissingSettingsAndIsIdempotent(): void {
    xinshi_api_update_10003();
    $upgraded = $this->storage->read(self::CONFIG);
    $this->assertSame(Yaml::parseFile($this->modulePath . '/config/install/xinshi_api.settings.yml'), $upgraded);
    xinshi_api_update_10003();
    $this->assertSame($upgraded, $this->storage->read(self::CONFIG));
  }

  public function testUpgradePreservesFallbacksAndLegacyCheckboxSelections(): void {
    $this->storage->write(self::CONFIG, [
      'page' => ['not_found' => '{"title":"Custom 404"}'],
      'cache_enable' => 1,
      'debug' => 0,
      'node_cache' => [
        'landing_page' => ['context' => ['user' => 'user']],
        'article' => ['context' => ['user' => 0]],
      ],
      'taxonomy_term_cache' => ['topics' => ['context' => ['user' => 'user']]],
      'user_cache' => ['user' => ['context' => ['user' => 0]]],
    ]);
    xinshi_api_update_10003();
    $data = $this->storage->read(self::CONFIG);
    $this->assertSame('{"title":"Custom 404"}', $data['page']['not_found']);
    $this->assertSame('', $data['page']['access_denied']);
    $this->assertTrue($data['cache_enable']);
    $this->assertFalse($data['debug']);
    foreach ([['node_cache', 'landing_page', TRUE], ['node_cache', 'article', FALSE], ['taxonomy_term_cache', 'topics', TRUE], ['user_cache', 'user', FALSE]] as [$group, $bundle, $selected]) {
      $context = $data[$group][$bundle]['context'];
      $this->assertSame($selected, (bool) $context['user']);
      $element = ['#default_value' => $context];
      $value = Checkboxes::valueCallback($element, FALSE, new FormState());
      $this->assertSame($selected, isset($value['user']), 'Existing selections survive Form API defaults.');
    }
    xinshi_api_update_10003();
    $this->assertSame($data, $this->storage->read(self::CONFIG));
  }

  public function testUpgradePreservesExplicitNullAndEmptyContainers(): void {
    $old = [
      'page' => NULL,
      'cache_enable' => FALSE,
      'node_cache' => NULL,
      'taxonomy_term_cache' => [],
      'user_cache' => ['user' => ['context' => NULL]],
      'debug' => TRUE,
    ];
    $this->storage->write(self::CONFIG, $old);
    xinshi_api_update_10003();
    $this->assertSame($old, $this->storage->read(self::CONFIG));
  }

  public function testUpgradeDoesNotPersistRuntimeOverrides(): void {
    $GLOBALS['config'][self::CONFIG] = ['cache_enable' => TRUE, 'page' => ['not_found' => 'runtime-only']];
    $this->assertTrue($this->factory->get(self::CONFIG)->get('cache_enable'));
    xinshi_api_update_10003();
    $data = $this->storage->read(self::CONFIG);
    $this->assertFalse($data['cache_enable']);
    $this->assertSame('', $data['page']['not_found']);
    $this->assertTrue($this->factory->get(self::CONFIG)->get('cache_enable'));
  }

  public function testUpgradeRetainsUnknownDeploymentSettings(): void {
    // Custom keys need their own schema extension; production still retains them.
    $this->dispatcher->removeSubscriber($this->checker);
    $this->storage->write(self::CONFIG, [
      'page' => ['access_denied' => '{"message":"Custom 403"}', 'custom' => 'keep'],
      'deployment_option' => ['value' => 9],
    ]);
    xinshi_api_update_10003();
    $data = $this->storage->read(self::CONFIG);
    $this->assertSame('{"message":"Custom 403"}', $data['page']['access_denied']);
    $this->assertSame('keep', $data['page']['custom']);
    $this->assertSame(['value' => 9], $data['deployment_option']);
  }

}

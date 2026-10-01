<?php

namespace Drupal\Tests\xinshi_sms\Unit;

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

/** Verifies SMS config typing and additive upgrades without a site or gateway. */
final class SmsSettingsUpdateTest extends TestCase {

  private const CONFIG = 'xinshi_sms.settings';
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
      $root . '/docroot/modules/contrib/smsframework/config/schema/sms.data_types.schema.yml',
      $root . '/docroot/modules/contrib/smsframework/config/schema/sms.schema.yml',
      $this->modulePath . '/config/schema/xinshi_sms.schema.yml',
    ] as $file) {
      $schema->write(basename($file, '.yml'), Yaml::parseFile($file));
    }
    if (is_file($this->modulePath . '/xinshi_sms.install')) {
      require_once $this->modulePath . '/xinshi_sms.install';
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

  public function testInstallDefaultsKeepExistingOptInBehavior(): void {
    $file = $this->modulePath . '/config/install/xinshi_sms.settings.yml';
    $this->assertFileExists($file);
    $defaults = Yaml::parseFile($file);
    $this->factory->getEditable(self::CONFIG)->setData($defaults)->save();
    $this->assertSame($defaults, $this->storage->read(self::CONFIG));
    $this->assertSame([
      'activate' => FALSE,
      'disable_register' => FALSE,
      'enabled_find_password' => FALSE,
      'override_reset_pass' => FALSE,
    ], $defaults);
  }

  public function testMissingSettingsUpgradeIsIdempotentAndIgnoresOverrides(): void {
    $GLOBALS['config'][self::CONFIG] = ['activate' => TRUE];
    $this->assertTrue($this->factory->get(self::CONFIG)->get('activate'));
    xinshi_sms_update_10001();
    $data = $this->storage->read(self::CONFIG);
    $this->assertSame(Yaml::parseFile($this->modulePath . '/config/install/xinshi_sms.settings.yml'), $data);
    $this->assertTrue($this->factory->get(self::CONFIG)->get('activate'));
    xinshi_sms_update_10001();
    $this->assertSame($data, $this->storage->read(self::CONFIG));
  }

  public function testPartialSettingsKeepChoicesAndLegacyAlibabaConfig(): void {
    $legacy = $this->alibabaSettings();
    $this->storage->write(self::CONFIG, [
      'activate' => 1,
      'disable_register' => 1,
      'override_reset_pass' => 0,
      'alibaba' => $legacy,
    ]);
    xinshi_sms_update_10001();
    $data = $this->storage->read(self::CONFIG);
    $this->assertTrue($data['activate']);
    $this->assertTrue($data['disable_register']);
    $this->assertFalse($data['enabled_find_password']);
    $this->assertFalse($data['override_reset_pass']);
    $this->assertEquals($legacy, $data['alibaba']);
    xinshi_sms_update_10001();
    $this->assertSame($data, $this->storage->read(self::CONFIG));
  }

  public function testExplicitNullAndDeploymentExtensionsAreRetained(): void {
    // Deployment extensions remain intact but must supply their own schema.
    $this->dispatcher->removeSubscriber($this->checker);
    $this->storage->write(self::CONFIG, ['activate' => NULL, 'alibaba' => NULL, 'deployment_option' => ['value' => 7]]);
    xinshi_sms_update_10001();
    $data = $this->storage->read(self::CONFIG);
    $this->assertNull($data['activate']);
    $this->assertNull($data['alibaba']);
    $this->assertSame(['value' => 7], $data['deployment_option']);
  }

  public static function gatewayPlugins(): array {
    return [['alibaba_send'], ['alibaba_dypnsapi_send']];
  }

  #[DataProvider('gatewayPlugins')]
  public function testGatewaySchemaResolvesByPluginAndPreservesSettings(string $plugin): void {
    $data = [
      'id' => 'test_gateway',
      'label' => 'Test gateway',
      'plugin' => $plugin,
      'settings' => $this->alibabaSettings(),
      'skip_queue' => FALSE,
      'retention_duration_incoming' => 0,
      'retention_duration_outgoing' => 0,
    ];
    $this->factory->getEditable('sms.gateway.test_gateway')->setData($data)->save();
    $this->assertEquals($data, $this->storage->read('sms.gateway.test_gateway'));
  }

  public function testLegacyStandaloneSettingsHaveSchemaWithoutChangingStorage(): void {
    $name = 'xinshi_sms.alibaba_sms_settings';
    $data = $this->alibabaSettings();
    $this->factory->getEditable($name)->setData($data)->save();
    $this->assertEquals($data, $this->storage->read($name));
    $this->assertFalse($this->storage->exists(self::CONFIG));
  }

  public function testTelephoneFormatterSettingKeepsMaskingSelection(): void {
    foreach ([0, 1] as $value) {
      $name = 'field.formatter.settings.xinshi_telephone';
      $this->factory->getEditable($name)->set('display_all', $value)->save();
      $this->assertSame((bool) $value, $this->storage->read($name)['display_all']);
    }
  }

  private function alibabaSettings(): array {
    return [
      'access_key_id' => 'test-only-key-id',
      'access_key_secret' => 'test-only-secret',
      'sign_name' => 'Test sign',
      'template_code' => 'SMS_TEST',
    ];
  }

}

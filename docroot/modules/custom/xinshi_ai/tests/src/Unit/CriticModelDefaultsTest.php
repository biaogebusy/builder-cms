<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\xinshi_ai\Controller\ModelManageController;
use Drupal\xinshi_ai\Controller\ModelRegistryController;
use Drupal\xinshi_ai\Service\ModelRegistryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Tests the CMS auxiliary roles through real configuration and API controllers. */
final class CriticModelDefaultsTest extends TestCase {

  private ConfigFactory $factory;
  private ModelRegistryService $registry;

  protected function setUp(): void {
    FileCacheFactory::setPrefix('critic-default-tests');
    $dispatcher = new EventDispatcher();
    $this->factory = new ConfigFactory(new MemoryStorage(), $dispatcher,
      $this->createMock(TypedConfigManagerInterface::class));
    $dispatcher->addSubscriber($this->factory);
    $this->registry = new ModelRegistryService($this->factory, new NullBackend('test'));
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->factory);
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    $modules = $this->createMock(ModuleExtensionList::class);
    $modules->method('getPath')->with('xinshi_ai')->willReturn(dirname(__DIR__, 3));
    $container->set('extension.list.module', $modules);
    \Drupal::setContainer($container);
    $this->factory->getEditable('xinshi_ai.models')->setData([
      'platforms' => ['xinshi' => ['enabled' => TRUE], 'custom' => ['enabled' => TRUE]],
      'models' => [
        ['id' => 'deepseek-flash', 'platform' => 'xinshi', 'capabilities' => ['chat']],
        ['id' => 'other-chat', 'platform' => 'xinshi', 'capabilities' => ['chat']],
        ['id' => 'image-model', 'platform' => 'xinshi', 'capabilities' => ['image']],
        ['id' => 'custom-chat', 'platform' => 'custom', 'capabilities' => ['chat']],
        ['id' => 'disabled-chat', 'platform' => 'xinshi', 'capabilities' => ['chat'], 'enabled' => FALSE],
      ],
      'defaults' => ['chat' => 'other-chat', 'image' => 'image-model'],
    ])->save();
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
  }

  public static function roles(): array {
    return [['critic', 'xinshi_ai_update_10005'], ['classifier', 'xinshi_ai_update_10006']];
  }

  #[DataProvider('roles')]
  public function testAdminCanSetSwitchAndClearCriticWithoutChangingChat(string $role, string $update): void {
    $controller = new ModelManageController($this->registry);
    $version = $this->registry->getVersion();
    foreach (['deepseek-flash', 'other-chat', NULL] as $id) {
      $response = $controller->updateDefaults(Request::create('/', 'PATCH', [], [], [], [],
        json_encode([$role => $id])));
      $this->assertSame(200, $response->getStatusCode());
      $defaults = $this->registry->getDefaults();
      $this->assertSame('other-chat', $defaults['chat']);
      $this->assertSame('image-model', $defaults['image']);
      $this->assertSame($id, $defaults[$role] ?? NULL);
      $public = (new ModelRegistryController($this->registry))->list(new Request());
      $this->assertSame($id, json_decode($public->getContent(), TRUE)['defaults'][$role] ?? NULL);
      $this->assertContains('config:xinshi_ai.models', $public->getCacheableMetadata()->getCacheTags());
      $this->assertNotSame($version, $this->registry->getVersion());
      $version = $this->registry->getVersion();
    }
  }

  public static function invalidDefaults(): array {
    $cases = [];
    foreach (['critic', 'classifier'] as $role) {
      foreach (['unknown', 'image-model', 'custom-chat', 'disabled-chat'] as $id) {
        $cases[] = [$role, $id];
      }
    }
    return $cases;
  }

  #[DataProvider('invalidDefaults')]
  public function testInvalidDefaultsAreRejectedAtomicallyAndNeverPublished(string $role, string $id): void {
    $before = $this->registry->getDefaults();
    $response = (new ModelManageController($this->registry))->updateDefaults(
      Request::create('/', 'PATCH', [], [], [], [], json_encode([
        'chat' => 'deepseek-flash', $role => $id,
      ])));
    $this->assertSame(422, $response->getStatusCode());
    $this->assertArrayHasKey($role, json_decode($response->getContent(), TRUE)['errors']);
    $this->assertSame($before, $this->registry->getDefaults());
    // Config imports can bypass management validation; the public endpoint still filters them.
    $this->factory->getEditable('xinshi_ai.models')->set('defaults.' . $role, $id)->save();
    $public = (new ModelRegistryController($this->registry))->list(new Request());
    $this->assertArrayNotHasKey($role, json_decode($public->getContent(), TRUE)['defaults']);
  }

  #[DataProvider('roles')]
  public function testDisablingTheGatewayHidesAndRejectsTheDefault(string $role, string $update): void {
    $this->factory->getEditable('xinshi_ai.models')->set('defaults.' . $role, 'deepseek-flash')
      ->set('platforms.xinshi.enabled', FALSE)->save();
    $response = (new ModelManageController($this->registry))->updateDefaults(
      Request::create('/', 'PATCH', [], [], [], [], json_encode([$role => 'deepseek-flash'])));
    $this->assertSame(422, $response->getStatusCode());
    $public = (new ModelRegistryController($this->registry))->list(new Request());
    $this->assertArrayNotHasKey($role, json_decode($public->getContent(), TRUE)['defaults'] ?? []);
  }

  #[DataProvider('roles')]
  public function testUpgradeSeedsTheCanonicalModelAndDefaultWithoutReplacingSiteModels(string $role, string $update): void {
    $config = $this->factory->getEditable('xinshi_ai.models');
    $models = array_values(array_filter($config->get('models'), fn(array $m) => $m['id'] !== 'deepseek-flash'));
    $config->set('models', $models)->save();
    $update();
    $seed = Yaml::parseFile(dirname(__DIR__, 3) . '/config/install/xinshi_ai.models.yml');
    $this->assertSame('deepseek-flash', $seed['defaults'][$role]);
    $this->assertSame('deepseek-flash', $config->get('defaults.' . $role));
    $this->assertSame('other-chat', $config->get('defaults.chat'));
    $this->assertSame($models, array_slice($config->get('models'), 0, count($models)));
    $this->assertSame(array_column($seed['models'], NULL, 'id')['deepseek-flash'],
      $this->registry->getModel('deepseek-flash'));
    $after = $config->getRawData();
    $update();
    $this->assertSame($after, $config->getRawData());
  }

  #[DataProvider('roles')]
  public function testUpgradePreservesExplicitDefaultAndCustomModelMetadata(string $role, string $update): void {
    $config = $this->factory->getEditable('xinshi_ai.models');
    $model = $this->registry->getModel('deepseek-flash') + ['label' => 'Site label'];
    $this->registry->saveModel($model);
    $config->set('defaults.' . $role, 'other-chat')->save();
    $update();
    $this->assertSame('other-chat', $config->get('defaults.' . $role));
    $this->assertSame($model, $this->registry->getModel('deepseek-flash'));
  }

  #[DataProvider('roles')]
  public function testUpgradeDoesNotEnableADisabledModelOrGateway(string $role, string $update): void {
    $config = $this->factory->getEditable('xinshi_ai.models');
    $model = $this->registry->getModel('deepseek-flash') + ['enabled' => FALSE];
    $this->registry->saveModel($model);
    $update();
    $this->assertNull($config->get('defaults.' . $role));
    $this->assertSame($model, array_column($config->get('models'), NULL, 'id')['deepseek-flash']);
    $config->set('platforms.xinshi.enabled', FALSE)->save();
    $before = $config->getRawData();
    $update();
    $this->assertSame($before, $config->getRawData());
  }

}

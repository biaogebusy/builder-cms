<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/** Tests additive view updates using core config storage, without a site. */
final class ImageJobsViewUpdateTest extends TestCase {

  private const CONFIG = 'views.view.image_jobs';
  private MemoryStorage $storage;
  private ConfigFactory $factory;
  private array $seed;
  private array $savedNames = [];
  private array $invalidatedTags = [];
  private ?array $fileCacheConfiguration;

  protected function setUp(): void {
    parent::setUp();
    $this->fileCacheConfiguration = FileCacheFactory::getConfiguration();
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    $modulePath = dirname(__DIR__, 3);
    $this->seed = (new FileStorage($modulePath . '/config/install'))->read(self::CONFIG);
    $this->storage = new MemoryStorage();
    $dispatcher = new EventDispatcher();
    // This test covers update persistence, not Views schema or plugin discovery.
    $this->factory = new ConfigFactory($this->storage, $dispatcher,
      $this->createMock(TypedConfigManagerInterface::class));
    $dispatcher->addSubscriber($this->factory);
    $dispatcher->addListener(ConfigEvents::SAVE, function (ConfigCrudEvent $event): void {
      $this->savedNames[] = $event->getConfig()->getName();
    });
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->method('invalidateTags')->willReturnCallback(function (array $tags): void {
      $this->invalidatedTags = array_merge($this->invalidatedTags, $tags);
    });
    $modules = $this->createMock(ModuleExtensionList::class);
    $modules->method('getPath')->with('xinshi_ai')->willReturn($modulePath);
    $container = new ContainerBuilder();
    $container->set('config.storage', $this->storage);
    $container->set('config.factory', $this->factory);
    $container->set('cache_tags.invalidator', $invalidator);
    $container->set('extension.list.module', $modules);
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    unset($GLOBALS['config'][self::CONFIG]);
    FileCacheFactory::setConfiguration($this->fileCacheConfiguration);
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public static function viewStatuses(): array {
    return [[TRUE], [FALSE]];
  }

  #[DataProvider('viewStatuses')]
  public function testOnlyMissingAdminDisplayIsAdded(bool $status): void {
    $original = $this->customizedView();
    $original['status'] = $status;
    $this->storage->write(self::CONFIG, $original);
    // Prime the immutable config cache, as another update hook might do.
    $this->assertNull($this->factory->get(self::CONFIG)->get('display.page_admin'));

    xinshi_ai_update_10001();
    $updated = $this->storage->read(self::CONFIG);
    $this->assertSame($this->seed['display']['page_admin'], $updated['display']['page_admin']);
    $this->assertSame('administer xinshi_ai', $updated['display']['page_admin']['display_options']['access']['options']['perm']);
    foreach ($this->seed['dependencies'] as $type => $dependencies) {
      foreach ($dependencies as $dependency) {
        $this->assertContains($dependency, $updated['dependencies'][$type]);
      }
    }
    foreach ($original['dependencies'] as $type => $dependencies) {
      if ($type === 'enforced') {
        $this->assertSame($dependencies, $updated['dependencies'][$type]);
      }
      else {
        foreach ($dependencies as $dependency) {
          $this->assertContains($dependency, $updated['dependencies'][$type]);
        }
      }
    }
    $preserved = $updated;
    unset($preserved['display']['page_admin']);
    $preserved['dependencies'] = $original['dependencies'];
    $this->assertSame($original, $preserved);
    $this->assertSame($updated['display']['page_admin'], $this->factory->get(self::CONFIG)->get('display.page_admin'));
    $this->assertContains('config:' . self::CONFIG, $this->invalidatedTags);
    $this->assertSame([self::CONFIG], $this->savedNames);

    xinshi_ai_update_10001();
    $this->assertSame($updated, $this->storage->read(self::CONFIG));
    $this->assertSame([self::CONFIG], $this->savedNames);
  }

  public function testExistingDisabledAdminDisplayIsNotReplaced(): void {
    $original = $this->customizedView();
    $original['display']['page_admin'] = $this->seed['display']['page_admin'];
    $original['display']['page_admin']['display_options']['enabled'] = FALSE;
    $original['display']['page_admin']['display_options']['path'] = 'admin/content/custom-image-jobs';
    $original['display']['page_admin']['display_options']['access']['options']['perm'] = 'administer site configuration';
    $this->storage->write(self::CONFIG, $original);

    xinshi_ai_update_10001();
    $this->assertSame($original, $this->storage->read(self::CONFIG));
    $this->assertSame([], $this->savedNames);
    $this->assertSame([], $this->invalidatedTags);
  }

  public function testDeletedViewIsNotRecreated(): void {
    xinshi_ai_update_10001();
    $this->assertFalse($this->storage->exists(self::CONFIG));
    $this->assertSame([], $this->savedNames);
    $this->assertSame([], $this->invalidatedTags);
  }

  public function testRuntimeOverridesAreNotPersisted(): void {
    $original = $this->customizedView();
    $this->storage->write(self::CONFIG, $original);
    $GLOBALS['config'][self::CONFIG] = [
      'label' => 'Runtime label',
      'status' => FALSE,
      'display' => ['page_admin' => ['display_options' => ['path' => 'runtime/image-jobs']]],
    ];
    $this->assertSame('Runtime label', $this->factory->get(self::CONFIG)->get('label'));

    xinshi_ai_update_10001();
    $updated = $this->storage->read(self::CONFIG);
    $this->assertSame($original['label'], $updated['label']);
    $this->assertSame($original['status'], $updated['status']);
    $this->assertSame($this->seed['display']['page_admin'], $updated['display']['page_admin']);
    $this->assertSame('runtime/image-jobs', $this->factory->get(self::CONFIG)->get('display.page_admin.display_options.path'));
    $this->assertFalse($this->factory->get(self::CONFIG)->get('status'));
  }

  private function customizedView(): array {
    $view = $this->seed;
    unset($view['display']['page_admin']);
    $view['uuid'] = 'f1852045-59c5-46bc-a642-4e76882e1b53';
    $view['langcode'] = 'zh-hans';
    $view['label'] = 'Site image history';
    $view['display']['default']['display_options']['pager']['options']['items_per_page'] = 7;
    $view['display']['default']['display_options']['filters']['status']['value'] = '0';
    $view['display']['rest_export']['display_options']['path'] = 'api/site-image-history';
    $view['display']['rest_export']['display_options']['access']['options']['perm'] = 'access content';
    $view['display']['site_export'] = $view['display']['rest_export'];
    $view['display']['site_export']['id'] = 'site_export';
    $view['dependencies'] = [
      'config' => ['node.type.image_job', 'field.storage.node.site_filter'],
      'module' => ['node', 'site_views'],
      'theme' => ['site_theme'],
      'enforced' => ['module' => ['site_feature']],
    ];
    return $view;
  }

}

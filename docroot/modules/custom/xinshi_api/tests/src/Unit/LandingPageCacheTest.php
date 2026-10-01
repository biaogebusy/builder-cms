<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\VariationCache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\DenyNoCacheRoutes;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\xinshi_api\Controller\LandingPageController;
use Drupal\xinshi_api\PageJsonResponseBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/** Exercises real controller/serializer/cache APIs with isolated rendering fixtures. */
final class LandingPageCacheTest extends TestCase {

  private ContainerBuilder $container;
  private RequestStack $requests;
  private MemoryBackend $cache;
  private array $settings = ['cache_enable' => TRUE, 'debug' => FALSE];
  private string $language = 'en';
  private string $interfaceLanguage = 'en';
  private int $revision = 10;
  private int $uid = 0;
  private string $permissions = 'public';
  private string $timezone = 'UTC';
  private int $now = 1000;
  private int $renders = 0;
  private int $maxAge = Cache::PERMANENT;
  private bool $published = TRUE;
  private bool $allowed = TRUE;
  private bool $exists = TRUE;
  private bool $alter = FALSE;
  private bool $renderError = FALSE;
  private array $viewBuild = [];
  private ?RenderContext $renderContext = NULL;

  protected function setUp(): void {
    $this->container = new ContainerBuilder();
    \Drupal::setContainer($this->container);
    $this->requests = new RequestStack();
    $this->container->set('request_stack', $this->requests);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturnCallback(fn() => $this->uid);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->uid !== 0);
    $this->container->set('current_user', $account);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $this->cache = new MemoryBackend($time);
    $this->container->set('cache.rest', $this->cache);
    $contextIds = ['url', 'user', 'user.permissions', 'languages', 'timezone'];
    foreach ($contextIds as $id) {
      $context = $this->createMock(CalculatedCacheContextInterface::class);
      $context->method('getContext')->willReturnCallback(fn($parameter = NULL) => (string) match ($id) {
        'url' => $this->requests->getCurrentRequest()->getUri(),
        'user' => $this->uid,
        'user.permissions' => $this->permissions,
        'languages' => $parameter === 'language_interface' ? $this->interfaceLanguage : $this->language,
        'timezone' => $this->timezone,
      });
      $context->method('getCacheableMetadata')->willReturn(new CacheableMetadata());
      $this->container->set('cache_context.' . $id, $context);
    }
    $contexts = new CacheContextsManager($this->container, $contextIds);
    $this->container->set('cache_contexts_manager', $contexts);
    $variationCache = new VariationCache($this->requests, $this->cache, $contexts);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn($key) => $this->settings[$key] ?? NULL);
    $config->method('getCacheTags')->willReturn(['config:xinshi_api.settings']);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with('xinshi_api.settings')->willReturn($config);
    $this->container->set('config.factory', $configs);

    // A product uses the same generic serializer without requiring a live node schema.
    $entity = $this->createMock(CacheTestEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('commerce_product');
    $entity->method('id')->willReturn(7);
    $entity->method('bundle')->willReturn('fixture');
    $entity->method('getRevisionId')->willReturnCallback(fn() => $this->revision);
    $entity->method('isPublished')->willReturnCallback(fn() => $this->published);
    $entity->method('language')->willReturnCallback(fn() => new Language(['id' => $this->language]));
    $entity->method('getCacheTags')->willReturn(['commerce_product:7']);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $entity->method('access')->willReturnCallback(function ($operation, $account = NULL, $as_object = FALSE) {
      $result = ($this->allowed ? AccessResult::allowed() : AccessResult::forbidden())
        ->addCacheTags(['access:fixture'])->addCacheContexts(['user.permissions']);
      return $as_object ? $result : $result->isAllowed();
    });
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(fn() => $this->exists ? $entity : NULL);
    $views = $this->createMock(EntityViewBuilderInterface::class);
    $views->method('view')->willReturnCallback(fn($entity, $mode) => [
      '#markup' => json_encode(['lang' => $this->language, 'revision' => $this->revision,
        'uid' => $this->uid, 'permissions' => $this->permissions, 'zone' => $this->timezone, 'mode' => $mode]),
      'view' => $this->viewBuild,
    ]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('commerce_product')->willReturn($storage);
    $entities->method('getViewBuilder')->willReturn($views);
    $this->container->set('entity_type.manager', $entities);
    $this->container->set('path_alias.manager', new class {
      public function getPathByAlias($path) { return $path; }
    });
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getCurrentLanguage')->willReturnCallback(fn() => new Language(['id' => $this->language]));
    $this->container->set('language_manager', $languages);
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('alter')->willReturnCallback(function ($hook, &$data, &$entity, &$metadata = NULL) {
      if ($this->alter) {
        $data['altered'] = TRUE;
        $data['category'] = $this->requests->getCurrentRequest()->query->get('category');
        if ($metadata instanceof CacheableMetadata) {
          $metadata->addCacheTags(['alter:fixture']);
        }
      }
    });
    $this->container->set('module_handler', $modules);
    $this->container->set('entity_theme_engine.entity_widget_service', new class {
      public function entityViewAlter(&$build, $entity, $mode) {}
    });
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('executeInRenderContext')->willReturnCallback(function ($context, $callback) {
      $this->renderContext = $context;
      return $callback();
    });
    $renderer->method('render')->willReturnCallback(function (&$build) {
      $this->renders++;
      if ($this->renderError) { throw new \RuntimeException('Rendering fixture failed'); }
      $metadata = (new BubbleableMetadata())->setCacheTags(['render:fixture'])
        ->setCacheContexts(['timezone'])->setCacheMaxAge($this->maxAge);
      $this->renderContext->push($metadata);
      $this->renderContext->bubble();
      return Markup::create($build['#markup']);
    });
    $this->container->set('renderer', $renderer);
    // The original controller ignores this service, allowing pre-fix reproduction.
    $this->container->register('xinshi_api.page_json_response', PageJsonResponseBuilder::class)
      ->setArguments([$configs, $variationCache, $renderer, $modules, $account, $time, new NullLogger()]);
  }

  private function request(array $query = []): CacheableJsonResponse {
    $request = Request::create('/api/v3/landingPage', 'GET', $query + ['content' => '/product/7']);
    $request->server->set('REQUEST_TIME', $this->now);
    if ($this->requests->getCurrentRequest()) { $this->requests->pop(); }
    $this->requests->push($request);
    return LandingPageController::create($this->container)->landingPage($request);
  }

  private function data(CacheableJsonResponse $response): array {
    return json_decode($response->getContent(), TRUE);
  }

  public function testLanguageAndRevisionNeverReuseOtherVariants(): void {
    foreach ([['en', 10], ['zh-hans', 10], ['en', 11], ['en', 10], ['zh-hans', 10]] as [$language, $revision]) {
      $this->language = $language;
      $this->revision = $revision;
      $data = $this->data($this->request());
      $this->assertSame($language, $data['lang']);
      $this->assertSame($revision, $data['revision']);
    }
    $this->assertSame(3, $this->renders);
  }

  public function testRenderVariantsAndMetadataSurviveHits(): void {
    $this->maxAge = 60;
    foreach (['UTC', 'Asia/Shanghai', 'UTC'] as $zone) {
      $this->timezone = $zone;
      $response = $this->request();
      $this->assertSame($zone, $this->data($response)['zone']);
      $metadata = $response->getCacheableMetadata();
      foreach (['commerce_product:7', 'access:fixture', 'render:fixture', 'config:xinshi_api.settings'] as $tag) {
        $this->assertContains($tag, $metadata->getCacheTags());
      }
      $this->assertContains('timezone', $metadata->getCacheContexts());
      $this->assertSame(60, $metadata->getCacheMaxAge());
    }
    $this->assertSame(2, $this->renders);
    $this->now += 40;
    $this->assertSame(20, $this->request()->getCacheableMetadata()->getCacheMaxAge());
    $this->now += 21;
    $this->request();
    $this->assertSame(3, $this->renders);
  }

  public function testAuthenticatedUsersAreUncacheableEvenWithSamePermissions(): void {
    foreach ([10, 11, 10] as $uid) {
      $this->uid = $uid;
      $response = $this->request();
      $this->assertSame($uid, $this->data($response)['uid']);
      $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
      $this->assertContains('user', $response->getCacheableMetadata()->getCacheContexts());
    }
    $this->assertSame(3, $this->renders);
  }

  public static function rowLifetimes(): array {
    return [[45], [0]];
  }

  #[DataProvider('rowLifetimes')]
  public function testUnbubbledViewsRowsControlPageCache(int $max_age): void {
    // Match the nested #rows shape read by renderLayoutBuilder(). The renderer
    // fixture deliberately does not bubble these dependencies to the view root.
    $this->viewBuild = [
      '#cache' => ['tags' => ['config:views.view.fixture']],
      '#rows' => [[
        '#cache' => ['tags' => ['view-group:fixture']],
        '#rows' => [[
          '#cache' => ['tags' => ['node:21'], 'contexts' => ['user.permissions'], 'max-age' => $max_age],
          '#access' => AccessResult::allowed()->addCacheTags(['row-access:fixture'])->addCacheContexts(['user']),
          'image' => ['#cache' => ['tags' => ['media:30']]],
        ]],
      ]],
      '#settings' => ['#cache' => ['tags' => ['not-rendered'], 'max-age' => 0]],
    ];
    foreach ([1, 2] as $unused) {
      $metadata = $this->request()->getCacheableMetadata();
      foreach (['node:21', 'media:30', 'row-access:fixture', 'view-group:fixture', 'config:views.view.fixture'] as $tag) {
        $this->assertContains($tag, $metadata->getCacheTags());
      }
      $this->assertNotContains('not-rendered', $metadata->getCacheTags());
      $this->assertContains('user', $metadata->getCacheContexts());
      $this->assertSame($max_age, $metadata->getCacheMaxAge());
    }
    $this->assertSame($max_age === 0 ? 2 : 1, $this->renders);
    foreach (['node:21', 'media:30'] as $tag) {
      $this->cache->invalidateTags([$tag]);
      $this->request();
    }
    $this->assertSame($max_age === 0 ? 4 : 3, $this->renders);
  }

  public function testAdditionalQueryParametersKeepDistinctPageOutput(): void {
    $this->alter = TRUE;
    foreach (['news', 'events', 'news'] as $category) {
      $response = $this->request(['category' => $category]);
      $this->assertSame($category, $this->data($response)['category']);
      $this->assertContains('url', $response->getCacheableMetadata()->getCacheContexts());
    }
    $this->assertSame(2, $this->renders);
  }

  public static function bypasses(): array {
    return [['noCache'], ['nocache'], ['preview']];
  }

  #[DataProvider('bypasses')]
  public function testExplicitBypass(string $flag): void {
    foreach ([1, 2] as $unused) {
      $this->assertSame(0, $this->request([$flag => 1])->getCacheableMetadata()->getCacheMaxAge());
    }
    $this->assertSame(2, $this->renders);
  }

  public function testDebugAndUncacheableRenderingNeverHitStoredData(): void {
    $this->request();
    $this->settings['debug'] = TRUE;
    $this->assertSame(0, $this->request()->getCacheableMetadata()->getCacheMaxAge());
    $this->assertSame(2, $this->renders);
    $this->settings['debug'] = FALSE;
    $this->cache->invalidateTags(['commerce_product:7']);
    $this->maxAge = 0;
    $this->assertSame(0, $this->request()->getCacheableMetadata()->getCacheMaxAge());
    $this->request();
    $this->assertSame(4, $this->renders);
  }

  public function testAccessAndPublicationAreRecheckedBeforeCacheHits(): void {
    $this->request();
    $this->allowed = FALSE;
    $denied = $this->request();
    $this->assertSame([], $this->data($denied));
    $this->assertSame(0, $denied->getCacheableMetadata()->getCacheMaxAge());
    $this->allowed = TRUE;
    $this->published = FALSE;
    $this->assertSame(0, $this->request()->getCacheableMetadata()->getCacheMaxAge());
    $this->assertSame(2, $this->renders);
    $this->exists = FALSE;
    $this->assertSame(0, $this->request()->getCacheableMetadata()->getCacheMaxAge());
  }

  public function testAlteredOutputIsIdenticalOnHitsAndInvalidates(): void {
    $this->alter = TRUE;
    $first = $this->request();
    $this->assertTrue($this->data($first)['altered']);
    $this->assertSame($first->getContent(), $this->request()->getContent());
    $this->assertContains('alter:fixture', $this->request()->getCacheableMetadata()->getCacheTags());
    $this->assertSame(1, $this->renders);
    foreach (['render:fixture', 'config:xinshi_api.settings', 'commerce_product:7'] as $tag) {
      $this->cache->invalidateTags([$tag]);
      $this->request();
    }
    $this->assertSame(4, $this->renders);
  }

  public function testRenderingErrorIsNeverCached(): void {
    $this->renderError = TRUE;
    $response = $this->request();
    $this->assertSame([], $this->data($response));
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
    $this->renderError = FALSE;
    $this->assertArrayHasKey('lang', $this->data($this->request()));
  }

  public function testPermissionInterfaceLanguageAndModeAreIndependent(): void {
    $this->request();
    $this->permissions = 'changed-field-permissions';
    $this->assertSame($this->permissions, $this->data($this->request())['permissions']);
    $this->interfaceLanguage = 'zh-hans';
    $this->request();
    $this->assertSame('full', $this->data($this->request(['mode' => 'full']))['mode']);
    $this->assertSame(4, $this->renders);
    $this->request(['mode' => 'full']);
    $this->assertSame(4, $this->renders);
  }

  public function testCacheDisabledStillReturnsRenderMetadata(): void {
    $this->settings['cache_enable'] = FALSE;
    $this->maxAge = 30;
    $this->request();
    $response = $this->request();
    $this->assertSame(2, $this->renders);
    $this->assertSame(30, $response->getCacheableMetadata()->getCacheMaxAge());
    $this->assertContains('timezone', $response->getCacheableMetadata()->getCacheContexts());
  }

  public function testCoreFullResponseCacheCannotOverridePageJsonPolicy(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_api.routing.yml');
    $definition = $routes['xinshi_api.v3.landingPage'];
    $route = new Route($definition['path'], $definition['defaults'], $definition['requirements'], $definition['options']);
    $policy = new DenyNoCacheRoutes(new RouteMatch('xinshi_api.v3.landingPage', $route));
    $this->assertSame(ResponsePolicyInterface::DENY, $policy->check($this->request(), $this->requests->getCurrentRequest()));
  }

}

/** Entity capabilities needed to exercise revision and publication cache policy. */
interface CacheTestEntityInterface extends EntityInterface, RevisionableInterface, EntityPublishedInterface {}

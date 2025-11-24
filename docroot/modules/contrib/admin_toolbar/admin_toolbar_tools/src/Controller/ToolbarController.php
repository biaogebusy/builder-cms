<?php

namespace Drupal\admin_toolbar_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for AdminToolbar Tools.
 *
 * @package Drupal\admin_toolbar_tools\Controller
 */
class ToolbarController extends ControllerBase {

  /**
   * A cron instance.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * A menu link manager instance.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * A context link manager instance.
   *
   * @var \Drupal\Core\Menu\ContextualLinkManager
   */
  protected $contextualLinkManager;

  /**
   * A local task manager instance.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   */
  protected $localTaskLinkManager;

  /**
   * A local action manager instance.
   *
   * @var \Drupal\Core\Menu\LocalActionManager
   */
  protected $localActionLinkManager;

  /**
   * A cache backend interface instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheRender;

  /**
   * A date time instance.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A plugin cache clear instance.
   *
   * @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface
   */
  protected $pluginCacheClearer;

  /**
   * The cache menu instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheMenu;

  /**
   * A TwigEnvironment instance.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The search theme.registry service.
   *
   * @var \Drupal\Core\Theme\Registry
   */
  protected $themeRegistry;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The CSS asset collection optimizer service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  protected $cssCollectionOptimizer;

  /**
   * The JavaScript asset collection optimizer service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  protected $jsCollectionOptimizer;

  /**
   * The asset query string service.
   *
   * @var \Drupal\Core\Asset\AssetQueryStringInterface
   */
  protected $assetQueryString;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cron = $container->get('cron');
    $instance->menuLinkManager = $container->get('plugin.manager.menu.link');
    $instance->contextualLinkManager = $container->get('plugin.manager.menu.contextual_link');
    $instance->localTaskLinkManager = $container->get('plugin.manager.menu.local_task');
    $instance->localActionLinkManager = $container->get('plugin.manager.menu.local_action');
    $instance->cacheRender = $container->get('cache.render');
    $instance->time = $container->get('datetime.time');
    $instance->requestStack = $container->get('request_stack');
    $instance->pluginCacheClearer = $container->get('plugin.cache_clearer');
    $instance->cacheMenu = $container->get('cache.menu');
    $instance->twig = $container->get('twig');
    $instance->themeRegistry = $container->get('theme.registry');
    $instance->cacheTagsInvalidator = $container->get('cache_tags.invalidator');
    $instance->cssCollectionOptimizer = $container->get('asset.css.collection_optimizer');
    $instance->jsCollectionOptimizer = $container->get('asset.js.collection_optimizer');

    // @todo Remove deprecated code when support for core:10.2 is dropped.
    if (floatval(\Drupal::VERSION) >= 10.2) {
      $instance->assetQueryString = $container->get('asset.query_string');
    }
    return $instance;
  }

  /**
   * Reload the previous page.
   *
   * @return string
   *   The URL to redirect to.
   */
  public function reloadPage() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request->server->get('HTTP_REFERER')) {
      return $request->server->get('HTTP_REFERER');
    }
    else {
      return base_path();
    }
  }

  /**
   * Flushes all caches.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushAll() {
    $this->messenger()->addMessage($this->t('All caches cleared.'));
    drupal_flush_all_caches();
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Flushes css and javascript caches.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushJsCss() {
    $this->cacheTagsInvalidator->invalidateTags(['library_info']);
    $this->cssCollectionOptimizer->deleteAll();
    $this->jsCollectionOptimizer->deleteAll();

    // @todo Remove deprecated code when support for core:10.2 is dropped.
    if (floatval(\Drupal::VERSION) < 10.2) {
      // @phpstan-ignore function.notFound
      _drupal_flush_css_js();
    }
    else {
      $this->assetQueryString->reset();
    }
    $this->messenger()->addMessage($this->t('CSS and JavaScript cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Flushes plugins caches.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushPlugins() {
    $this->pluginCacheClearer->clearCachedDefinitions();
    $this->messenger()->addMessage($this->t('Plugins cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Resets all static caches.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushStatic() {
    drupal_static_reset();
    $this->messenger()->addMessage($this->t('Static cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears all cached menu data.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushMenu() {
    $this->cacheMenu->deleteAll();
    $this->menuLinkManager->rebuild();
    $this->contextualLinkManager->clearCachedDefinitions();
    $this->localTaskLinkManager->clearCachedDefinitions();
    $this->localActionLinkManager->clearCachedDefinitions();
    $this->messenger()->addMessage($this->t('Routing and links cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears all cached views data.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushViews() {
    views_invalidate_cache();
    $this->messenger()->addMessage($this->t('Views cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears the twig cache.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function flushTwig() {
    $this->twig->invalidate();
    $this->messenger()->addMessage($this->t('Twig cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Run the cron.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function runCron() {
    $this->cron->run();
    $this->messenger()->addMessage($this->t('Cron ran successfully.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clear the rendered cache.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function cacheRender() {
    $this->cacheRender->deleteAll();
    $this->messenger()->addMessage($this->t('Render cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Rebuild the theme registry.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the previous page.
   */
  public function themeRebuild() {
    $this->themeRegistry->reset();
    $this->messenger()->addMessage($this->t('Theme registry rebuilt.'));
    return new RedirectResponse($this->reloadPage());
  }

}

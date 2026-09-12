<?php

namespace Drupal\webform\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides route responses for Webform CSS/JS assets.
 */
class WebformAssetsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Returns the webform module's global CSS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Drupal\Core\Cache\CacheableResponse
   *   The response object.
   */
  public function css(Request $request) {
    $config = $this->config('webform.settings');
    $css = $config->get('assets.css');
    if (empty($css)) {
      return $this->getNotFoundResponse($request);
    }

    $response = new CacheableResponse($css, 200, ['Content-Type' => 'text/css']);
    return $response->addCacheableDependency($config);
  }

  /**
   * Returns the webform module's global JavaScript.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Drupal\Core\Cache\CacheableResponse
   *   The response object.
   */
  public function javascript(Request $request) {
    $config = $this->config('webform.settings');
    $javascript = $config->get('assets.javascript');
    if (empty($javascript)) {
      return $this->getNotFoundResponse($request);
    }

    $response = new CacheableResponse($javascript, 200, ['Content-Type' => 'text/javascript']);
    return $response->addCacheableDependency($config);
  }

  /**
   * Prepare a 404 response.
   *
   * The fast_404 feature can cause a cache invalidation issue for
   * anonymous users. To fix it we need to add a similar response
   * with all required cache tags instead of the default one.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   *
   * @see Fast404ExceptionHtmlSubscriber::on404()
   */
  protected function getNotFoundResponse(Request $request) {
    $config = $this->configFactory->get('system.performance');
    $exclude_paths = $config->get('fast_404.exclude_paths');
    if ($config->get('fast_404.enabled') && $exclude_paths && !preg_match($exclude_paths, $request->getPathInfo())) {
      $fast_paths = $config->get('fast_404.paths');
      if ($fast_paths && preg_match($fast_paths, $request->getPathInfo())) {
        $fast_404_html = strtr($config->get('fast_404.html'), ['@path' => Html::escape($request->getUri())]);
        $response = new HtmlResponse($fast_404_html, Response::HTTP_NOT_FOUND);
        // Some routes such as system.files conditionally throw a
        // NotFoundHttpException depending on URL parameters instead of just
        // the route and route parameters, so add the URL cache context
        // to account for this.
        $cacheable_metadata = new CacheableMetadata();
        $cacheable_metadata->setCacheContexts(['url']);
        $cacheable_metadata->addCacheTags(['4xx-response']);
        $response
          ->addCacheableDependency($cacheable_metadata)
          ->addCacheableDependency($this->config('webform.settings'));
        return $response;
      }
    }

    // Follow the default exception otherwise.
    throw new NotFoundHttpException();
  }

}

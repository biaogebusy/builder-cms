<?php

namespace Drupal\entity_print\Asset;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;

/**
 * Render CSS assets for the entities being printed.
 */
class AssetRenderer implements AssetRendererInterface {

  /**
   * The asset resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * The css asset renderer.
   *
   * @var \Drupal\Core\Asset\CssCollectionRenderer
   */
  protected $cssRenderer;

  /**
   * Asset collector.
   *
   * @var \Drupal\entity_print\Asset\AssetCollectorInterface
   */
  protected $assetCollector;

  /**
   * AssetRenderer constructor.
   *
   * @param \Drupal\Core\Asset\AssetResolverInterface $asset_resolver
   *   The asset resolver.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $css_renderer
   *   The CSS renderer.
   * @param \Drupal\entity_print\Asset\AssetCollectorInterface $asset_collector
   *   The asset collector.
   */
  public function __construct(AssetResolverInterface $asset_resolver, AssetCollectionRendererInterface $css_renderer, AssetCollectorInterface $asset_collector) {
    $this->assetResolver = $asset_resolver;
    $this->cssRenderer = $css_renderer;
    $this->assetCollector = $asset_collector;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $entities, $use_default_css = TRUE, $optimize_css = TRUE) {
    $build['#attached']['library'] = [];

    // Inject some generic CSS across all templates.
    if ($use_default_css) {
      $build['#attached']['library'][] = 'entity_print/default';
    }

    $build['#attached']['library'] = array_merge($this->assetCollector->getCssLibraries($entities), $build['#attached']['library']);

    // This keeps BC for the CSS alter event which used to provide a render
    // array but now passes a list of libraries. So, if users of the API have
    // treated it like a render array, we move the libraries into the correct
    // place.
    foreach ($build['#attached']['library'] as $key => $library) {
      if ($key === '#attached') {
        $build['#attached']['library'] = array_merge($build['#attached']['library'], $library['library']);
        unset($build['#attached']['library'][$key]);
      }
    }

    $css_assets = $this->assetResolver->getCssAssets(AttachedAssets::createFromRenderArray($build), $optimize_css);
    return $this->cssRenderer->render($css_assets);
  }

}

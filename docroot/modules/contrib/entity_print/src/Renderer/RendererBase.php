<?php

namespace Drupal\entity_print\Renderer;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\entity_print\Asset\AssetRendererInterface;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PrintHtmlAlterEvent;
use Drupal\entity_print\FilenameGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Render\RendererInterface as CoreRendererInterface;

/**
 * The RendererBase class.
 */
abstract class RendererBase implements RendererInterface, EntityHandlerInterface {

  /**
   * The renderer for renderable arrays.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The asset renderer.
   *
   * @var \Drupal\entity_print\Asset\AssetRendererInterface
   */
  protected $assetRenderer;

  /**
   * Generate filename's for a printed document.
   *
   * @var \Drupal\entity_print\FilenameGeneratorInterface
   */
  protected $filenameGenerator;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * RendererBase constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Cores renderer.
   * @param \Drupal\entity_print\Asset\AssetRendererInterface $asset_renderer
   *   The asset renderer.
   * @param \Drupal\entity_print\FilenameGeneratorInterface $filename_generator
   *   Filename generator.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(CoreRendererInterface $renderer, AssetRendererInterface $asset_renderer, FilenameGeneratorInterface $filename_generator, EventDispatcherInterface $event_dispatcher) {
    $this->renderer = $renderer;
    $this->assetRenderer = $asset_renderer;
    $this->filenameGenerator = $filename_generator;
    $this->dispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('renderer'),
      $container->get('entity_print.asset_renderer'),
      $container->get('entity_print.filename_generator'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generateHtml(array $entities, array $render, $use_default_css, $optimize_css) {
    $rendered_css = $this->assetRenderer->render($entities, $use_default_css, $optimize_css);
    $render['#entity_print_css'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$rendered_css) {
      return $this->renderer->render($rendered_css);
    });

    $html = (string) $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$render) {
      return $this->renderer->render($render, TRUE);
    });

    // Allow other modules to alter the generated HTML.
    $this->dispatcher->dispatch(new PrintHtmlAlterEvent($html, $entities), PrintEvents::POST_RENDER);

    return $html;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(array $entities) {
    return $this->filenameGenerator->generateFilename($entities);
  }

}

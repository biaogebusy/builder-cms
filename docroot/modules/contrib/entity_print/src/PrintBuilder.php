<?php

namespace Drupal\entity_print;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PreSendPrintEvent;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use Drupal\entity_print\Renderer\RendererFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The print builder service.
 */
class PrintBuilder implements PrintBuilderInterface {

  use StringTranslationTrait;

  /**
   * The Print Renderer factory.
   *
   * @var \Drupal\entity_print\Renderer\RendererFactoryInterface
   */
  protected $rendererFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Constructs a new EntityPrintPrintBuilder.
   *
   * @param \Drupal\entity_print\Renderer\RendererFactoryInterface $renderer_factory
   *   The Renderer factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(RendererFactoryInterface $renderer_factory, EventDispatcherInterface $event_dispatcher, TranslationInterface $string_translation) {
    $this->rendererFactory = $renderer_factory;
    $this->dispatcher = $event_dispatcher;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function deliverPrintable(array $entities, PrintEngineInterface $print_engine, $force_download = FALSE, $use_default_css = TRUE) {
    $renderer = $this->prepareRenderer($entities, $print_engine, $use_default_css);

    // Allow other modules to alter the generated Print object.
    $this->dispatcher->dispatch(new PreSendPrintEvent($print_engine, $entities), PrintEvents::PRE_SEND);

    // Calculate the filename.
    $filename = $renderer->getFilename($entities) . '.' . $print_engine->getExportType()->getFileExtension();

    return $print_engine->send($filename, $force_download);
  }

  /**
   * {@inheritdoc}
   */
  public function printHtml(EntityInterface $entity, $use_default_css = TRUE, $optimize_css = TRUE) {
    $renderer = $this->rendererFactory->create([$entity]);
    $content[] = $renderer->render([$entity]);

    $render = [
      '#theme' => 'entity_print__' . $entity->getEntityTypeId() . '__' . $entity->bundle(),
      '#title' => $this->t('View'),
      '#content' => $content,
      '#attached' => [],
    ];
    return $renderer->generateHtml([$entity], $render, $use_default_css, $optimize_css);
  }

  /**
   * {@inheritdoc}
   */
  public function savePrintable(array $entities, PrintEngineInterface $print_engine, $scheme = 'public', $filename = FALSE, $use_default_css = TRUE) {
    $renderer = $this->prepareRenderer($entities, $print_engine, $use_default_css);

    // Allow other modules to alter the generated Print object.
    $this->dispatcher->dispatch(new PreSendPrintEvent($print_engine, $entities), PrintEvents::PRE_SEND);

    // If we didn't have a URI passed in the generate one.
    if (!$filename) {
      $filename = $renderer->getFilename($entities) . '.' . $print_engine->getExportType()->getFileExtension();
    }

    $uri = "$scheme://$filename";

    // Save the file.
    return \Drupal::service('file_system')->saveData($print_engine->getBlob(), $uri, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * Configure the print engine with the passed entities.
   *
   * @param array $entities
   *   An array of entities.
   * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
   *   The print engine.
   * @param bool $use_default_css
   *   TRUE if we want the default CSS included.
   *
   * @return \Drupal\entity_print\Renderer\RendererInterface
   *   A print renderer.
   */
  protected function prepareRenderer(array $entities, PrintEngineInterface $print_engine, $use_default_css) {
    if (empty($entities)) {
      throw new \InvalidArgumentException('You must pass at least 1 entity');
    }

    $renderer = $this->rendererFactory->create($entities);
    $content = $renderer->render($entities);

    $first_entity = reset($entities);
    $render = [
      '#theme' => 'entity_print__' . $first_entity->getEntityTypeId() . '__' . $first_entity->bundle(),
      '#title' => $this->t('View @type', ['@type' => $print_engine->getExportType()->label()]),
      '#content' => $content,
      '#attached' => [],
    ];

    $print_engine->addPage($renderer->generateHtml($entities, $render, $use_default_css, TRUE));

    return $renderer;
  }

}

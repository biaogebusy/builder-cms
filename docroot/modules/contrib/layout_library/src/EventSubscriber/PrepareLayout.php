<?php

namespace Drupal\layout_library\EventSubscriber;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_library\Entity\Layout;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters a layout override to use layout library selection over the default.
 *
 * @package Drupal\layout_library\EventSubscriber
 */
class PrepareLayout implements EventSubscriberInterface {

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * PrepareLayout constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The tempstore repository.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Only available in >= D9.1, see
    // https://www.drupal.org/project/layout_library/issues/3082434.
    if (class_exists('Drupal\layout_builder\Event\PrepareLayoutEvent')) {
      // Priority higher than Layout Builder to interact first.
      $events[LayoutBuilderEvents::PREPARE_LAYOUT] = ['onPrepareLayout', 20];
      return $events;
    }
    return [];
  }

  /**
   * Prepares a layout for use in the UI.
   *
   * @param \Drupal\layout_builder\Event\PrepareLayoutEvent $event
   *   The prepare layout event.
   */
  public function onPrepareLayout(PrepareLayoutEvent $event) {
    $section_storage = $event->getSectionStorage();

    // If the layout has pending changes, do nothing.
    if ($this->layoutTempstoreRepository->has($section_storage)) {
      return;
    }

    // If the layout is an override that has not yet been overridden, copy the
    // sections from the corresponding selected library layout.
    elseif ($section_storage instanceof OverridesSectionStorageInterface && !$section_storage->isOverridden()) {
      $entity = $section_storage->getContextValue('entity');
      if ($entity instanceof FieldableEntityInterface && $entity->hasField('layout_selection') && !$entity->get('layout_selection')->isEmpty()) {
        $layout = $entity->get('layout_selection')->entity;
        if ($layout instanceof Layout) {
          $sections = $layout->getLayout();
          foreach ($sections as $section) {
            $section_storage->appendSection($section);
          }
          $this->layoutTempstoreRepository->set($section_storage);
        }
      }
    }
  }

}

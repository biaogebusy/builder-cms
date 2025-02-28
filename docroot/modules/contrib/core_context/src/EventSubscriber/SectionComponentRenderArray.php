<?php

namespace Drupal\core_context\EventSubscriber;

use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to a render array being generated for a layout section component.
 */
final class SectionComponentRenderArray implements EventSubscriberInterface {

  /**
   * The section storage manager service.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  private $sectionStorageManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * BlockComponentRenderArray constructor.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // This needs to run before Layout Builder's event subscriber, so its
      // priority needs to be higher.
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => ['setComponentContexts', 150],
    ];
  }

  /**
   * Sets context values on a section component at render time.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The event object.
   */
  public function setComponentContexts(SectionComponentBuildRenderArrayEvent $event) {
    $plugin = $event->getPlugin();

    // If the plugin cannot accept contexts, there's no point in continuing.
    if (! ($plugin instanceof ContextAwarePluginInterface)) {
      return;
    }

    // @todo Remove when https://www.drupal.org/project/drupal/issues/3018782 is
    // done.
    // @see \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::buildSections()
    $contexts = $event->getContexts();
    if (isset($contexts['layout_builder.entity']) && empty($contexts['entity'])) {
      $contexts['entity'] = &$contexts['layout_builder.entity'];
    }

    // The event is unaware of the section storage, so we need to use the
    // available contexts to find the correct section storage.
    $section_storage = $this->sectionStorageManager->findByContext($contexts, $event->getCacheableMetadata());

    // If the section storage is overriding another one, the contexts provided
    // by the override should be overlaid on top of the ones provided by the
    // underlying default.
    if (is_null($section_storage)) {
      return;
    }
    $contexts = $this->getContextsFromSectionStorage($section_storage);
    while ($section_storage instanceof OverridesSectionStorageInterface) {
      $section_storage = $section_storage->getDefaultSectionStorage();
      $contexts += $this->getContextsFromSectionStorage($section_storage);
    }

    // Filter out any contexts which the plugin does not recognize.
    $contexts = array_intersect_key($contexts, $plugin->getContextDefinitions());

    foreach ($contexts as $name => $context) {
      $plugin->setContextValue($name, $context->getContextValue());
    }
  }

  /**
   * Extracts contexts from a section storage plugin.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage plugin from which to extract contexts.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   The contexts extracted from the section storage.
   */
  private function getContextsFromSectionStorage(SectionStorageInterface $section_storage) {
    // Since we need to get the section list by prying open the section storage,
    // we can only work with instances of SectionStorageBase, since they have a
    // protected getSectionList() method. This isn't very clean, but I spoke to
    // Tim Plunkett and he literally told me to do it this way.
    if ($section_storage instanceof SectionStorageBase) {
      $method = new \ReflectionMethod($section_storage, 'getSectionList');
      $method->setAccessible(TRUE);
      /** @var \Drupal\layout_builder\SectionListInterface $section_list */
      $section_list = $method->invoke($section_storage);
    }
    else {
      return [];
    }

    // If the section list is an entity field, we need to get the whole entity
    // since that's what we can extract contexts from.
    if ($section_list instanceof FieldItemListInterface) {
      $section_list = $section_list->getEntity();
    }

    // If the section list still isn't an entity, then we don't have a way to
    // extract contexts from it.
    if (! ($section_list instanceof EntityInterface)) {
      return [];
    }

    // If the entity doesn't have a context handler, then we cannot get contexts
    // from it and there is nothing else to do.
    if (! $section_list->getEntityType()->hasHandlerClass('context')) {
      return [];
    }

    /** @var \Drupal\Component\Plugin\Context\ContextInterface[] $contexts */
    return $this->entityTypeManager
      ->getHandler($section_list->getEntityTypeId(), 'context')
      ->getContexts($section_list);
  }

}

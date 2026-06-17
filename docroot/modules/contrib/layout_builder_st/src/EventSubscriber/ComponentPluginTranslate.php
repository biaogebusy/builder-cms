<?php

namespace Drupal\layout_builder_st\EventSubscriber;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder_st\TranslationsHelperTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates the plugin configuration if needed.
 *
 * @internal
 *   Tagged services are internal.
 */
final class ComponentPluginTranslate implements EventSubscriberInterface {

  use LayoutEntityHelperTrait;
  // This trait will be merged into core's.
  use TranslationsHelperTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Creates a ComponentPluginTranslate object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(LanguageManagerInterface $language_manager, RouteMatchInterface $route_match) {
    $this->languageManager = $language_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 200];
    return $events;
  }

  /**
   * Translates the plugin configuration if needed.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    if (!$this->languageManager->isMultilingual()) {
      return;
    }
    $plugin = $event->getPlugin();
    $contexts = $event->getContexts();
    $component = $event->getComponent();
    if (!$plugin instanceof ConfigurableInterface && !isset($contexts['layout_builder.entity'])) {
      return;
    }

    // @todo Change to 'entity' in https://www.drupal.org/node/3018782.
    if (!empty($contexts['layout_builder.entity'])) {
      $entity = $contexts['layout_builder.entity']->getContextValue();
      $configuration = $plugin->getConfiguration();
      if ($event->inPreview()) {
        $section_storage = $this->routeMatch->getParameter('section_storage');
      }
      else {
        $section_storage = $this->getSectionStorageForEntity($entity);
      }

      if (static::isTranslation($section_storage)) {
        if ($translated_plugin_configuration = $section_storage->getTranslatedComponentConfiguration($component->getUuid())) {
          $translated_plugin_configuration += $configuration;
          $plugin->setConfiguration($translated_plugin_configuration);
        }
      }
    }
  }

}

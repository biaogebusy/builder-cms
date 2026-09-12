<?php

namespace Drupal\core_context;

use Drupal\core_context\EventSubscriber\SectionComponentRenderArray;
use Drupal\core_context\ContextProvider\LayoutBuilder;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers container services on behalf of Core Context.
 */
final class CoreContextServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    if (isset($modules['layout_builder'])) {
      $container->register('core_context.render_section_component_subscriber')
        ->setClass(SectionComponentRenderArray::class)
        ->setArguments([
          new Reference('plugin.manager.layout_builder.section_storage'),
          new Reference('entity_type.manager'),
        ])
        ->addTag('event_subscriber');

      $container->register('core_context.layout_builder')
        ->setClass(LayoutBuilder::class)
        ->setArguments([
          new Reference('entity_display.repository'),
          new Reference('core_context.canonical_entity'),
          new Reference('current_route_match'),
          new Reference('entity_type.manager'),
        ])
        ->addTag('core_context.context_provider');
    }
  }

}

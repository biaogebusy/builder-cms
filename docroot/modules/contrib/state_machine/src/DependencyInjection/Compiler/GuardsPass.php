<?php

namespace Drupal\state_machine\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds the context provider service IDs to the context manager.
 */
class GuardsPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   *
   * Passes the grouped service IDs of guards to the guard factory.
   */
  public function process(ContainerBuilder $container) {
    $guards = [];
    $priorities = [];
    foreach ($container->findTaggedServiceIds('state_machine.guard') as $id => $attributes) {
      if (empty($attributes[0]['group'])) {
        // Guards without a specified group should be invoked for all of them.
        $attributes[0]['group'] = '_generic';
      }

      $group_id = $attributes[0]['group'];
      $guards[$group_id][$id] = $id;
      $priorities[$group_id][$id] = $attributes[0]['priority'] ?? 0;
    }

    // Sort the guards by priority.
    foreach ($priorities as $group_id => $services) {
      array_multisort($priorities[$group_id], SORT_DESC, $guards[$group_id]);
    }

    $definition = $container->getDefinition('state_machine.guard_factory');
    $definition->addArgument($guards);
  }

}

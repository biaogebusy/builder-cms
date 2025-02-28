<?php

namespace Drupal\state_machine;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\state_machine\DependencyInjection\Compiler\GuardsPass;

/**
 * Registers the guard compiler pass.
 */
class StateMachineServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new GuardsPass());
  }

}

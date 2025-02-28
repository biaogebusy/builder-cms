<?php

namespace Drupal\slick;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Provides ServiceProvider to cross-compat from D8 ~ D10+.
 *
 * @see https://www.drupal.org/node/3080612
 * @see https://www.drupal.org/project/drupal/issues/3074585
 * @todo remove this class when D8 is dropped in #3263027
 */
class SlickServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    if (version_compare(\Drupal::VERSION, '9.0', '<')) {
      $container->getDefinition('slick.skin_manager')
        ->setArgument(0, '@app.root');
    }
  }

}

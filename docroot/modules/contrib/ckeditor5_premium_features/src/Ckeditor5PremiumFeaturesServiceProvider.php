<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Defines a service provider for the CKEditor 5 Premium Features module.
 */
class Ckeditor5PremiumFeaturesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');
    assert(is_array($modules));

    // Only register the installer service if the package_manager module is installed.
    if (array_key_exists('package_manager', $modules)) {
      if (version_compare(\Drupal::VERSION, '11.2.0', '>=')) {
        $container->register('ckeditor5_premium_features.installer', \Drupal\ckeditor5_premium_features\ComposerInstaller\Installer::class)
          ->setAutowired(TRUE);
      }
      else {
        $container->register('ckeditor5_premium_features.installer', \Drupal\ckeditor5_premium_features\ComposerInstaller\LegacyInstaller::class)
          ->setAutowired(TRUE);
      }
    }
  }

}

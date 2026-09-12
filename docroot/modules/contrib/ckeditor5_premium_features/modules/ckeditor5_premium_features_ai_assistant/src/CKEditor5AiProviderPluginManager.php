<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant;

use Drupal\ckeditor5_premium_features_ai_assistant\Annotation\CKEditor5AiProvider;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * CKEditor5AiProvider plugin manager.
 */
final class CKEditor5AiProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/CKEditor5AiProvider', $namespaces, $module_handler, CKEditor5AiProviderInterface::class, CKEditor5AiProvider::class);
    $this->alterInfo('ckeditor5_ai_provider_info');
    $this->setCacheBackend($cache_backend, 'ckeditor5_ai_provider_plugins');
  }

  /**
   * Return all available AI providers.
   *
   * @return array
   */
  public function getAllProviders(): array {
    return $this->getDefinitions() ?? [];
  }

}

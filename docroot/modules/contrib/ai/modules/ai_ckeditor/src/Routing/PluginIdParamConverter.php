<?php

namespace Drupal\ai_ckeditor\Routing;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for a custom ID.
 */
class PluginIdParamConverter implements ParamConverterInterface {

  /**
   * Handles parameter upcasting for AI CKEditor plugins.
   *
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $pluginManager
   *   The plugin manager.
   */
  public function __construct(protected AiCKEditorPluginManager $pluginManager) {}

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return isset($definition['type']) && $definition['type'] == 'ai_ckeditor_plugin';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    try {
      $plugin = $this->pluginManager->createInstance($value);
      return ($plugin instanceof AiCKEditorPluginInterface) ? $plugin : NULL;
    }
    catch (PluginNotFoundException $e) {
      return NULL;
    }
  }

}

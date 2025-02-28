<?php

namespace Drupal\footable;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 *
 */
class FooTable implements FooTableInterface {

  /**
   * The FooTable breakpoint storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $breakpointStorage;

  /**
   * The FooTable config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a FooTable object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory) {
    $this->breakpointStorage = $entityTypeManager->getStorage('footable_breakpoint');
    $this->config = $configFactory->get('footable.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary() {
    $library = $this->config->get('plugin_type') . '_' . $this->config->get('plugin_compression');
    return 'footable/footable_' . $library;
  }

  /**
   * {@inheritdoc}
   */
  public function getBreakpoints() {
    $breakpoints = [];

    /* @var \Drupal\footable\Entity\FooTableBreakpointInterface $breakpoint */
    foreach ($this->breakpointStorage->loadMultiple() as $breakpoint) {
      $breakpoints[$breakpoint->id()] = $breakpoint->getBreakpoint();
    }

    return $breakpoints;
  }

}

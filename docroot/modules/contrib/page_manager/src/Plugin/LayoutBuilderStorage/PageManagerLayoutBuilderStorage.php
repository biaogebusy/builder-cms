<?php

namespace Drupal\page_manager\Plugin\LayoutBuilderStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\page_manager\Plugin\DisplayVariant\LayoutBuilderDisplayVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Page Manager storage service that stores Layout Builder displays.
 */
class PageManagerLayoutBuilderStorage extends PluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PageManagerLayoutBuilderStorage.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Load a page variant entity.
   *
   * @param string $id
   *   The page variant entity's id.
   *
   * @return \Drupal\page_manager\PageVariantInterface
   *   The variant object.
   *
   * @throws \Exception
   */
  protected function loadPageVariant($id) {
    return $this->entityTypeManager->getStorage('page_variant')->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function save(LayoutBuilderDisplayVariant $lb_display) {
    $id = $lb_display->getStorageId();
    if ($id && ($page_variant = $this->loadPageVariant($id))) {
      $variant_plugin = $page_variant->getVariantPlugin();
      if (!($variant_plugin instanceof LayoutBuilderDisplayVariant)) {
        throw new \Exception("Page variant doesn't use a Layout Builder display variant");
      }
      $variant_plugin->setConfiguration($lb_display->getConfiguration());
      $page_variant->save();
    }
    else {
      throw new \Exception("Couldn't find page variant to store Layout Builder display");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    if ($page_variant = $this->loadPageVariant($id)) {
      $lb_display = $page_variant->getVariantPlugin();

      // If this page variant doesn't have a Panels display on it, then we treat
      // it the same as if there was no such page variant.
      if (!($lb_display instanceof LayoutBuilderDisplayVariant)) {
        return NULL;
      }

      // Pass down the contexts because the display has no other way to get them
      // from the variant.
      $lb_display->setContexts($page_variant->getContexts());

      return $lb_display;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($id, $op, AccountInterface $account) {
    if ($op == 'change layout') {
      $op = 'update';
    }
    if ($page_variant = $this->loadPageVariant($id)) {
      return $page_variant->access($op, $account, TRUE);
    }

    return AccessResult::forbidden();
  }

}

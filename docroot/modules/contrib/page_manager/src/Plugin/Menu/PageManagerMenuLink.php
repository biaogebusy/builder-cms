<?php

namespace Drupal\page_manager\Plugin\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines menu links provided by Page manager.
 *
 * @see \Drupal\page_manager\Plugin\Derivative\PageManagerMenuLink
 */
class PageManagerMenuLink extends MenuLinkBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $overrideAllowed = [
    'menu_name' => 1,
    'parent' => 1,
    'weight' => 1,
    'expanded' => 1,
    'enabled' => 1,
    'title' => 1,
    'description' => 1,
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The static menu link service used to store updates to weight/parent etc.
   *
   * @var \Drupal\Core\Menu\StaticMenuLinkOverridesInterface
   */
  protected $staticOverride;

  /**
   * The page of the menu link.
   *
   * @var \Drupal\page_manager\PageInterface|null
   */
  protected $page;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new PageManagerMenuLink.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $static_override
   *   The static menu link override storage.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, StaticMenuLinkOverridesInterface $static_override, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->staticOverride = $static_override;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('menu_link.static.overrides'),
      $container->get('logger.channel.page_manager')
    );
  }

  /**
   * Initializes the proper page.
   *
   * @return \Drupal\page_manager\PageInterface|null
   *   The page, or NULL if it could not be loaded.
   */
  public function loadPage() {
    if (empty($this->page)) {
      $metadata = $this->getMetaData();
      if (empty($metadata['page_id'])) {
        return NULL;
      }
      try {
        $this->page = $this->entityTypeManager->getStorage('page')->load($metadata['page_id']);
      }
      catch (\Exception $e) {
        $this->logger->error($e->getMessage());
      }
    }
    return $this->page;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    // A description entered on the menu link edit form is stored as an
    // override on the plugin definition and wins over the page description.
    if (isset($this->pluginDefinition['description'])) {
      return (string) $this->pluginDefinition['description'];
    }
    $page = $this->loadPage();
    return $page ? (string) $page->getDescription() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function isExpanded() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    // Filter the list of updates to only those that are allowed.
    $overrides = array_intersect_key($new_definition_values, $this->overrideAllowed);
    // Update the definition.
    $this->pluginDefinition = $overrides + $this->getPluginDefinition();
    if ($persist) {
      $this->staticOverride->saveOverride($this->getPluginId(), $this->pluginDefinition);
    }

    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink() {}

}

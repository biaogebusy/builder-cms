<?php

namespace Drupal\page_manager\Plugin\PageManagerMenu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\page_manager\Plugin\MenuBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Normal menu type for Page Manager.
 *
 * @PageManagerMenu(
 *   id = "normal_menu",
 *   label = @Translation("Normal menu entry")
 * )
 */
class NormalMenu extends MenuBase implements ContainerFactoryPluginInterface {

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $linkManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('plugin.manager.menu.link'), $container->get('entity_type.manager'), $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Constructs a NormalMenu.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $link_manager
   *   The link manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(MenuLinkManagerInterface $link_manager, EntityTypeManagerInterface $entity_type_manager, array $configuration, $plugin_id, $plugin_definition) {
    $this->linkManager = $link_manager;
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate() {
    $this->linkManager->rebuild();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('If set to normal or tab, enter the text to use for the menu item.'),
      '#default_value' => $this->configuration['title'],
    ];

    $menus = [];
    foreach ($this->entityTypeManager->getStorage('menu')->loadMultiple() as $id => $menu) {
      $menus[$id] = $menu->label();
    }
    $form['menu_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#options' => $menus,
      '#default_value' => $this->configuration['menu_name'],
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('The lower the weight the higher/further left it will appear.'),
      '#default_value' => $this->configuration['weight'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'title' => '',
      'menu_name' => '',
      'weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['menu_name'] = $form_state->getValue('menu_name');
    $this->configuration['weight'] = (int) $form_state->getValue('weight');
  }

}

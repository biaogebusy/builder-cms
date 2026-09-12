<?php

namespace Drupal\page_manager\Plugin\PageManagerMenu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tab menu type for Page Manager.
 *
 * @PageManagerMenu(
 *   id = "default_menu_tab",
 *   label = @Translation("Default menu tab")
 * )
 */
class DefaultMenuTab extends MenuTab {

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
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.menu.local_task'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Constructs a DefaultMenuTab.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $task_manager
   *   The local task manager.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LocalTaskManagerInterface $task_manager, array $configuration, $plugin_id, $plugin_definition) {
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct($task_manager, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'parent_menu_type' => 'none',
      'parent_title' => '',
      'parent_menu' => 'main',
      'parent_weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['parent_menu_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Parent menu type'),
      '#options' => [
        'none' => $this->t('No menu entry'),
        'menu' => $this->t('Normal menu entry'),
        'task' => $this->t('Menu tab'),
      ],
      '#default_value' => $this->configuration['parent_menu_type'],
    ];
    $form['parent_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parent title'),
      '#description' => $this->t('If set to normal or tab, enter the text to use for the menu item.'),
      '#states' => [
        'visible' => [
          ':input[name="menu_type_settings[menu_settings][parent_menu_type]"]' => [
            ['value' => 'menu'],
            ['value' => 'task'],
          ],
        ],
      ],
      '#default_value' => $this->configuration['parent_title'],
    ];
    $menus = [];
    foreach ($this->entityTypeManager->getStorage('menu')->loadMultiple() as $id => $menu) {
      $menus[$id] = $menu->label();
    }
    $form['parent_menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent menu'),
      '#options' => $menus,
      '#states' => [
        'visible' => [
          ':input[name="menu_type_settings[menu_settings][parent_menu_type]"]' => ['value' => 'menu'],
        ],
      ],
      '#default_value' => $this->configuration['parent_menu'],
    ];

    $form['parent_weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Parent weight'),
      '#description' => $this->t('The lower the weight the higher/further left it will appear.'),
      '#states' => [
        'visible' => [
          ':input[name="menu_type_settings[menu_settings][parent_menu_type]"]' => [
            ['value' => 'menu'],
            ['value' => 'task'],
          ],
        ],
      ],
      '#default_value' => $this->configuration['parent_weight'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['parent_menu_type'] = $form_state->getValue('parent_menu_type');
    $this->configuration['parent_title'] = $form_state->getValue('parent_title');
    $this->configuration['parent_menu'] = $form_state->getValue('parent_menu');
    $this->configuration['parent_weight'] = (int) $form_state->getValue('parent_weight');
    parent::submitConfigurationForm($form, $form_state);
  }

}

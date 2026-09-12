<?php

namespace Drupal\page_manager\Plugin\PageManagerMenu;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\page_manager\Plugin\MenuBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tab menu type for Page Manager.
 *
 * @PageManagerMenu(
 *   id = "menu_tab",
 *   label = @Translation("Menu tab")
 * )
 */
class MenuTab extends MenuBase implements ContainerFactoryPluginInterface {

  /**
   * The local task manager.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   */
  protected $taskManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('plugin.manager.menu.local_task'), $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Constructs a MenuTab.
   *
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $task_manager
   *   The local task manager.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(LocalTaskManagerInterface $task_manager, array $configuration, $plugin_id, $plugin_definition) {
    $this->taskManager = $task_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate() {
    $this->taskManager->clearCachedDefinitions();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'title' => '',
      'weight' => 0,
    ];
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['weight'] = (int) $form_state->getValue('weight');
  }

}

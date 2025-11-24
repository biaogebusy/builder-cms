<?php

namespace Drupal\admin_toolbar_tools\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for AdminToolbar Tools.
 *
 * @package Drupal\admin_toolbar_tools\Form
 */
class AdminToolbarToolsSettingsForm extends ConfigFormBase {

  /**
   * The cache menu instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheMenu;

  /**
   * The menu link manager instance.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * {@inheritdoc}
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cacheMenu = $container->get('cache.menu');
    $instance->menuLinkManager = $container->get('plugin.manager.menu.link');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string>
   *   An array of configuration names that this form is responsible for.
   */
  protected function getEditableConfigNames() {
    return [
      'admin_toolbar_tools.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_toolbar_tools_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form array with the form elements.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('admin_toolbar_tools.settings');
    $form['max_bundle_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of bundle sub-menus to display'),
      '#description' => $this->t('Loading a large number of items can cause performance issues.'),
      '#default_value' => $config->get('max_bundle_number'),
      '#min' => 1,
      '#max' => 500,
    ];

    $form['show_local_tasks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable local tasks display'),
      '#description' => $this->t('Local tasks such as node edit and delete.'),
      '#default_value' => $config->get('show_local_tasks'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return void
   *   Nothing to return.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('admin_toolbar_tools.settings')
      ->set('max_bundle_number', $form_state->getValue('max_bundle_number'))
      ->set('show_local_tasks', $form_state->getValue('show_local_tasks'))
      ->save();
    parent::submitForm($form, $form_state);
    $this->cacheMenu->deleteAll();
    $this->menuLinkManager->rebuild();
  }

}

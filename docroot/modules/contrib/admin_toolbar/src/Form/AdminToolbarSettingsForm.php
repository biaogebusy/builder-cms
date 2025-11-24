<?php

namespace Drupal\admin_toolbar\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminToolbarSettingsForm. The config form for the admin_toolbar module.
 *
 * @package Drupal\admin_toolbar\Form
 */
class AdminToolbarSettingsForm extends ConfigFormBase {

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
   * {@inheritDoc}
   *
   * @return array<string>
   *   An array of configuration names that this form is responsible for.
   */
  protected function getEditableConfigNames() {
    return [
      'admin_toolbar.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'admin_toolbar_settings';
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
    $config = $this->config('admin_toolbar.settings');

    // Add an introduction text to module's settings form.
    $form['settings_form_help_intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('The Admin Toolbar module provides a better user experience for the default Drupal Toolbar.<br>It is a drop-down menu that allows quicker access to all the administration pages in a more efficient way, with fewer clicks and less scrolling.<br><br>The following settings mostly provide advanced configuration of the JavaScript behavior of the Toolbar sticky and hoverIntent.'),
    ];

    // Add 'sticky behavior' wrapper as a 'fieldset' so it stays displayed.
    $form['sticky_options_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toolbar sticky behavior'),
    ];
    // Add 'sticky behavior' radio field with options.
    $form['sticky_options_wrapper']['sticky_behavior'] = [
      '#type' => 'radios',
      '#prefix' => $this->t("By default, the Admin Toolbar sticky behavior is <em>enabled</em> so it stays at the top of the browser window when scrolling up or down.<br>Select <em>Disabled</em> to disable Admin Toolbar's sticky behavior so it stays at the top of the page when scrolling up/down and does not follow the browser window."),
      '#options' => [
        'enabled' => $this->t('Enabled'),
        'hide_on_scroll_down' => $this->t('Disabled, show on scroll-up'),
        'disabled' => $this->t('Disabled'),
      ],
      '#default_value' => $config->get('sticky_behavior') ?: 'enabled',
    ];

    // Checkbox field to enable/disable the shortcut for toggling the toolbar.
    $form['sticky_options_wrapper']['enable_toggle_shortcut'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide or show the toolbar with shortcut (Alt + p)'),
      '#description' => $this->t('If set, the toolbar will be hidden or visible when the user presses the keys: "Alt + p".<br>Disable this setting if it conflicts with any existing keyboard configuration.'),
      '#default_value' => $config->get('enable_toggle_shortcut'),
    ];

    /* Add hoverIntent form settings. */

    // Add hoverIntent behavior wrapper as a 'fieldset' so it stays displayed.
    $form['hoverintent_behavior'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toolbar hoverIntent behavior'),
      '#tree' => TRUE,
    ];

    // Create link to hoverIntent source website.
    $hoverintent_source_link = new TranslatableMarkup('<a href=":hoverintent_src_url" target="_blank">hoverIntent</a>', [':hoverintent_src_url' => 'https://briancherne.github.io/jquery-hoverIntent/']);

    // Add enable hoverIntent behavior checkbox.
    $form['hoverintent_behavior']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable hoverIntent'),
      '#prefix' => $this->t(
        "Provides a smoother user experience, where only menu items which are paused over are expanded, to avoid accidental activations.<br>Disable @hoverintent_source_link to use module's default basic JavaScript behavior.",
        ['@hoverintent_source_link' => $hoverintent_source_link]
      ),
      '#default_value' => $config->get('hoverintent_behavior')['enabled'] ?? TRUE,
    ];

    // Add hoverIntent timeout field as a select with a range of integer values.
    $timeout_range_values = range(250, 2000, 250);
    $form['hoverintent_behavior']['timeout'] = [
      '#type' => 'select',
      '#title' => $this->t('hoverIntent timeout (ms)'),
      '#field_suffix' => $this->t('milliseconds'),
      '#description' => $this->t('Sets the hoverIntent trigger timeout (steps of 250).<br>The higher the value, the longer the menu dropdown stays visible, after the mouse moves out (default: 500ms).'),
      '#options' => array_combine($timeout_range_values, $timeout_range_values),
      '#default_value' => $config->get('hoverintent_behavior')['timeout'],
      // Display the timeout field if hoverIntent is enabled.
      '#states' => [
        'visible' => [
          ':input[name="hoverintent_behavior[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* Add Advanced form settings. */

    // Add 'Advanced settings' wrapper as 'details' so it could be collapsed.
    $form['advanced_settings_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#description' => $this->t('Advanced settings for the Admin Toolbar module.'),
      // Collapsed by default.
      '#open' => FALSE,
    ];

    // Add maximum 'Menu depth' select field with a range of values: 1 to 9.
    $depth_values = range(1, 9);
    $form['advanced_settings_wrapper']['menu_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu depth'),
      '#description' => $this->t('Maximum depth of displayed nested menu items.'),
      '#default_value' => $config->get('menu_depth'),
      '#options' => array_combine($depth_values, $depth_values),
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
    $this->config('admin_toolbar.settings')
      ->set('enable_toggle_shortcut', $form_state->getValue('enable_toggle_shortcut'))
      ->set('menu_depth', $form_state->getValue('menu_depth'))
      ->set('sticky_behavior', $form_state->getValue('sticky_behavior'))
      ->set('hoverintent_behavior', $form_state->getValue('hoverintent_behavior'))
      ->save();
    parent::submitForm($form, $form_state);
    $this->cacheMenu->deleteAll();
    $this->menuLinkManager->rebuild();
  }

}

<?php

namespace Drupal\admin_toolbar_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Admin Toolbar Search settings for this site.
 */
class AdminToolbarSearchSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_toolbar_search_admin_toolbar_search_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string>
   *   An array of configuration names that this form is responsible for.
   */
  protected function getEditableConfigNames() {
    return ['admin_toolbar_search.settings'];
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
    // Get the Admin Toolbar Search settings configuration.
    $admin_toolbar_search_settings = $this->config('admin_toolbar_search.settings');

    // Checkbox field to control the display of the search input as a menu item.
    $form['display_menu_item'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the search input as a menu item.'),
      '#description' => $this->t("If set, instead of displaying a text input field, it displays a menu item in the toolbar so the user has to click on it to toggle the search input."),
      '#default_value' => $admin_toolbar_search_settings->get('display_menu_item'),
    ];
    // Checkbox field to enable/disable the keyboard shortcut.
    $form['enable_keyboard_shortcut'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable keyboard shortcut (Alt + a)'),
      '#description' => $this->t('If set, the search input will be focused when the user presses the keys: "Alt + a".<br>Disable this setting if it conflicts with any existing keyboard configuration.'),
      '#default_value' => $admin_toolbar_search_settings->get('enable_keyboard_shortcut'),
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
    $this->config('admin_toolbar_search.settings')
      ->set('display_menu_item', $form_state->getValue('display_menu_item'))
      ->set('enable_keyboard_shortcut', $form_state->getValue('enable_keyboard_shortcut'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}

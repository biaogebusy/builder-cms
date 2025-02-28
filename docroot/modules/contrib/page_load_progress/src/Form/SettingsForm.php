<?php

namespace Drupal\page_load_progress\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Allows to configure the page_load_progress module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_load_progress_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'page_load_progress.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $page_load_progress_config = $this->config('page_load_progress.settings');

    $form['page_load_progress_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Time to wait before locking the screen'),
      '#description' => $this->t('Enter the time you want to wait before showing the image lock.'),
      '#options' => [
        10   => $this->t('Show immediately'),
        1000 => $this->t('Show after a 1 second delay'),
        3000 => $this->t('Show after a 3 seconds delay'),
        5000 => $this->t('Show after a 5 seconds delay'),
      ],
      '#default_value' => $page_load_progress_config->get('page_load_progress_time'),
    ];

    $form['visibility_conditions'] = [
      '#type' => 'details',
      '#title' => $this->t('Visibility conditions'),
      '#open' => TRUE,
    ];

    $form['visibility_conditions']['page_load_progress_request_path'] = [
      '#type' => 'textarea',
      '#default_value' => $page_load_progress_config->get('page_load_progress_request_path'),
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is %user-wildcard for every user page. %front is the front page.", [
        '%user-wildcard' => '/user/*',
        '%front' => '<front>',
      ]),
    ];

    $form['visibility_conditions']['page_load_progress_request_path_negate_condition'] = [
      '#type' => 'radios',
      '#default_value' => (int) $page_load_progress_config->get('page_load_progress_request_path_negate_condition'),
      '#options' => [
        $this->t('Show for the listed pages'),
        $this->t('Hide for the listed pages'),
      ],
    ];

    $form['visibility_conditions']['page_load_progress_internal_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the throbber for internal links'),
      '#description' => $this->t("The throbber won't be triggered on links opened from a modal or when the user is trying to open them in a new tab."),
      '#default_value' => $page_load_progress_config->get('page_load_progress_internal_links'),
    ];

    $form['page_load_progress_esc_key'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow ESC key to kill the throbber'),
      '#description' => $this->t('Under some circumstances, you may want to let your user unlock the screen manually by pressing the ESC key.'),
      '#default_value' => $page_load_progress_config->get('page_load_progress_esc_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('page_load_progress.settings')
      ->set('page_load_progress_time', $form_state->getValue('page_load_progress_time'))
      ->set('page_load_progress_request_path', $form_state->getValue('page_load_progress_request_path'))
      ->set('page_load_progress_request_path_negate_condition', $form_state->getValue('page_load_progress_request_path_negate_condition'))
      ->set('page_load_progress_internal_links', $form_state->getValue('page_load_progress_internal_links'))
      ->set('page_load_progress_esc_key', $form_state->getValue('page_load_progress_esc_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

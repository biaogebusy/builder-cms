<?php

namespace Drupal\tour\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the class.
 *
 * @internal
 */
class TourSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'tour.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tour_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('tour.settings');
    $tour_avail_text = $config->get('tour_avail_text');
    $tour_no_avail_text = $config->get('tour_no_avail_text');

    $form['tour'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configure button label'),
    ];
    $form['tour']['hide_tour_when_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide tour when empty'),
      '#description' => $this->t('On pages without a tour hide the button.'),
      '#default_value' => $config->get('hide_tour_when_empty'),
    ];
    $form['tour']['display_custom_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display custom labels'),
      '#description' => $this->t('Default will be "Tour" and "No tour".'),
      '#default_value' => $config->get('display_custom_labels'),
    ];
    $form['tour']['tour_avail_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tour available'),
      '#description' => $this->t('Button label for pages with a tour available'),
      '#default_value' => $tour_avail_text,
      '#states' => [
        'invisible' => [
          ':input[name="display_custom_labels"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['tour']['tour_no_avail_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No tour available'),
      '#description' => $this->t('Button label for pages with no tour available'),
      '#default_value' => $tour_no_avail_text,
      '#states' => [
        'invisible' => [
          ':input[name="display_custom_labels"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['tour']['enable_recap'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable tour recap'),
      '#description' => $this->t('Show a "View steps" button at the end of tours that opens a recap of all steps in a new window.'),
      '#default_value' => $config->get('enable_recap'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#button_type' => 'primary',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->config('tour.settings')
      ->set('hide_tour_when_empty', $form_state->getValue('hide_tour_when_empty'))
      ->set('display_custom_labels', $form_state->getValue('display_custom_labels'))
      ->set('tour_avail_text', $form_state->getValue('tour_avail_text'))
      ->set('tour_no_avail_text', $form_state->getValue('tour_no_avail_text'))
      ->set('enable_recap', $form_state->getValue('enable_recap'))
      ->save();
    Cache::invalidateTags(['tour_settings']);
  }

}

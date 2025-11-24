<?php

namespace Drupal\webform_rest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a Webform REST configuration form.
 */
class WebformRestForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\user\RestForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_rest';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'webform_rest.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form structure.
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {  
    //Get current configuration
    $config = $this->config('webform_rest.settings');
    
    //Build form
    $form['confirmation_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmation Settings'),
      '#description' => $this->t('If checked, confirmation settings from webform will be sent in the submit resource response. Leave blank to only return sid.'),
      '#default_value' => $config->get('confirmation_settings') ?: '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
   $this->configFactory->getEditable('webform_rest.settings')
     ->set('confirmation_settings', $form_state->getValue('confirmation_settings'))
     ->save();

   parent::submitForm($form, $form_state);
  }

}

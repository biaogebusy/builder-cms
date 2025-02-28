<?php

namespace Drupal\pendo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PendoAdminForm.
 */
class PendoAdminForm extends ConfigFormBase {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new PendoAdminForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($config_factory);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pendo.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pendo_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pendo.settings');

    // @see https://www.drupal.org/docs/7/api/localization-api/dynamic-or-static-links-and-html-in-translatable-strings
    $form['link'] = [
      '#markup' => $this->t('Pendo links: <a href="@pendo-admin" target="_blank">Admin Settings</a><br /><br />', [
        '@pendo-admin' => Url::fromUri('https://app.pendo.io/admin', ['attributes' => ['target' => '_blank']])->toString(),
      ]),
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pendo API Key'),
      '#description' => $this->t('This key will be visible to the public in the HTML source code and is not intended to be secret.'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('pendo.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    $this->messenger()->addMessage("You may need to clear caches for your new API key to correctly load in page markup.", MessengerInterface::TYPE_STATUS);
  }

}

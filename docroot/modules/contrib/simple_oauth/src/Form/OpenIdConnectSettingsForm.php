<?php

namespace Drupal\simple_oauth\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 *
 * @internal
 */
class OpenIdConnectSettingsForm extends ConfigFormBase {

  /**
   * The claim names.
   *
   * @var string[]
   */
  private $claimNames;

  /**
   * Oauth2TokenSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param string[] $claim_names
   *   The names of the claims.
   */
  public function __construct(ConfigFactoryInterface $config_factory, array $claim_names) {
    parent::__construct($config_factory);
    $this->claimNames = $claim_names;
  }

  /**
   * Creates the form.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\simple_oauth\Form\OpenIdConnectSettingsForm
   *   The form.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->getParameter('simple_oauth.openid.claims')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'openid_connect_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_oauth.settings'];
  }

  /**
   * Defines the settings form for Access Token entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['disable_openid_connect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable OpenID Connect'),
      '#description' => $this->t('Disable OpenID Connect if you have a conflicting custom or contributed implementation of OpenID Connect in your site.'),
      '#default_value' => $this->config('simple_oauth.settings')
        ->get('disable_openid_connect'),
    ];
    $form['info'] = [
      '#type' => 'container',
      'customize' => [
        '#markup' => '<p>' . $this->t('Check the <a href="@href" rel="noopener" target="_blank">Simple OAuth guide</a> for OpenID Connect to learn how to customize the user claims for OpenID Connect.', [
            '@href' => Url::fromUri('https://www.drupal.org/node/3172149')
              ->toString(),
          ]) . '</p>',
      ],
      'claims' => [
        '#type' => 'checkboxes',
        '#title' => $this->t('Available claims'),
        '#description' => $this->t('Claims are defined and managed in the service container. They are only listed here for reference. Please see the documentation above for more information.'),
        '#options' => array_combine($this->claimNames, $this->claimNames),
        '#default_value' => $this->claimNames,
        '#disabled' => TRUE,
      ],
      '#states' => [
        'invisible' => [
          ':input[name="disable_openid_connect"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $disable = $form_state->getValue('disable_openid_connect');
    $config = $this->config('simple_oauth.settings');
    $config->set('disable_openid_connect', $disable);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}

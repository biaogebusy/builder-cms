<?php

namespace Drupal\simple_oauth\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 *
 * @internal
 */
class OpenIdConnectSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

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
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param string[] $claim_names
   *   The names of the claims.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, array $claim_names) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->claimNames = $claim_names;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->getParameter('simple_oauth.openid.claims')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openid_connect_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['disable_openid_connect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable OpenID Connect'),
      '#description' => $this->t('Disable OpenID Connect if you have a conflicting custom or contributed implementation of OpenID Connect in your site.'),
      '#config_target' => 'simple_oauth.settings:disable_openid_connect',
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

}

<?php

namespace Drupal\social_auth\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\Plugin\Network\NetworkInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures Social Auth settings.
 */
class SocialAuthSettingsForm extends ConfigFormBase {

  /**
   * Network manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  protected NetworkManager $networkManager;

  /**
   * Route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->networkManager = $container->get('plugin.network.manager');
    $instance->routeProvider = $container->get('router.route_provider');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'social_auth_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    // Disregard this because we must get network settings.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NetworkInterface $network = NULL): array {
    $form_state->set('network', $network);

    $network_id = $network->getBaseId();
    if ($network->getDerivativeId()) {
      $network_id = "{$network->getBaseId()}.{$network->getDerivativeId()}";
    }
    $network_config = $this->configFactory->get("$network_id.settings");

    $form['network'] = [
      '#type' => 'details',
      '#title' => $this->t('@network settings', ['@network' => $network->getPluginDefinition()['social_network']]),
      '#open' => TRUE,
    ];

    $form['network']['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#default_value' => $network_config->get('client_id'),
      '#description' => $this->t('Copy the Client ID here.'),
    ];

    $form['network']['client_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client secret'),
      '#default_value' => $network_config->get('client_secret'),
      '#description' => $this->t('Copy the Client Secret here.'),
    ];

    $form['network']['authorized_redirect_url'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#title' => $this->t('Authorized redirect URL'),
      '#default_value' => $network->getCallbackUrl()->setAbsolute()->toString(),
    ];

    $form['network']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['network']['advanced']['scopes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Scopes for API call'),
      '#default_value' => $network_config->get('scopes'),
      '#description' => $this->t('Define any additional scopes to be requested, separated by commas'),
    ];

    $form['network']['advanced']['endpoints'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API calls to be made to collect data'),
      '#default_value' => $network_config->get('endpoints'),
      '#description' => $this->t('Define the Endpoints to be requested when user authenticates for the first time.<br>
                                  Enter each endpoint in different lines in the format <em>endpoint</em>|<em>name_of_endpoint</em>.'),
    ];

    $social_auth_config = $this->configFactory->get('social_auth.settings');

    $form['social_auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Social Auth Settings'),
      '#open' => TRUE,
      '#description' => $this->t('These settings allow you to configure how Social Auth module behaves on your Drupal site'),
    ];

    $form['social_auth']['post_login'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Post login path'),
      '#description' => $this->t('Path where the user should be redirected after a successful login. It must begin with <em>/, #</em> or <em>?</em>.'),
      '#default_value' => $social_auth_config->get('post_login'),
    ];

    $form['social_auth']['user_allowed'] = [
      '#type' => 'radios',
      '#title' => $this->t('What can users do?'),
      '#default_value' => $social_auth_config->get('user_allowed'),
      '#options' => [
        'register' => $this->t('Register and login'),
        'login' => $this->t('Login only'),
      ],
    ];

    $form['social_auth']['redirect_user_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect new users to Drupal user form'),
      '#description' => $this->t('If you check this, new users are redirected to Drupal user form after the user is created. This is useful if you want to encourage users to fill in additional user fields.'),
      '#default_value' => $social_auth_config->get('redirect_user_form'),
    ];

    $form['social_auth']['disable_admin_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Social Auth login for administrator'),
      '#description' => $this->t('Disabling Social Auth login for administrator (<em>user 1</em>) can help protect your site if a security vulnerability is ever discovered in some Social Network PHP SDK or this module.'),
      '#default_value' => $social_auth_config->get('disable_admin_login'),
    ];

    // Option to disable Social Auth for specific roles.
    // phpcs:ignore
    $roles = Role::loadMultiple();
    $options = [];
    foreach ($roles as $key => $role_object) {
      if ($key != 'anonymous' && $key != 'authenticated') {
        $options[$key] = Html::escape($role_object->get('label'));
      }
    }

    $form['social_auth']['disabled_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disable Social Auth login for the following roles'),
      '#options' => $options,
      '#default_value' => $social_auth_config->get('disabled_roles'),
    ];
    if (empty($roles)) {
      $form['social_auth']['disabled_roles']['#description'] = $this->t('No roles found.');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $post_login = $values['post_login'];

    // If it is not a valid path.
    if (!in_array($post_login[0], ["/", "#", "?"])) {
      $form_state->setErrorByName('post_login', $this->t('The path is not valid. It must begin with <em>/, #</em> or <em>?</em>'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->configFactory->getEditable('social_auth.settings')
      ->set('post_login', $values['post_login'])
      ->set('user_allowed', $values['user_allowed'])
      ->set('redirect_user_form', $values['redirect_user_form'])
      ->set('disable_admin_login', $values['disable_admin_login'])
      ->set('disabled_roles', $values['disabled_roles'])
      ->save();

    /** @var \Drupal\social_auth\Plugin\Network\NetworkInterface $network */
    $network = $form_state->get('network');
    $network_id = $network->getBaseId();
    if ($network->getDerivativeId()) {
      $network_id = "{$network->getBaseId()}.{$network->getDerivativeId()}";
    }
    $this->configFactory->getEditable("$network_id.settings")
      ->set('client_id', $values['client_id'])
      ->set('client_secret', $values['client_secret'])
      ->set('scopes', $values['scopes'])
      ->set('endpoints', $values['endpoints'])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Provides the page title for the form.
   *
   * @param \Drupal\social_auth\Plugin\Network\NetworkInterface $network
   *   Network.
   *
   * @return string
   *   The page title.
   */
  public function getTitle(NetworkInterface $network): string {
    return $this->t("@network user authentication", [
      '@network' => $network->getPluginDefinition()['social_network'],
    ]);
  }

}

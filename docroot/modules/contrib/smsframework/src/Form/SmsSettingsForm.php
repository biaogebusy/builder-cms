<?php

declare(strict_types = 1);

namespace Drupal\sms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\sms\Entity\SmsGateway;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for SMS settings.
 */
class SmsSettingsForm extends ConfigFormBase {

  /**
   * Constructs a new SmsSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $routeBuilder
   *   The route builder.
   * @param \Drupal\Core\Routing\RequestContext $requestContext
   *   The request context.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected RouteBuilderInterface $routeBuilder,
    protected RequestContext $requestContext,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('router.builder'),
      $container->get('router.request_context'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $sms_settings = $this->config('sms.settings');

    $gateways = [];
    /** @var \Drupal\sms\Entity\SmsGatewayInterface $gateway */
    foreach (SmsGateway::loadMultiple() as $gateway) {
      $gateways[$gateway->id()] = $gateway->label();
    }

    $form['fallback_gateway'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback gateway'),
      '#description' => $this->t('Gateway to use if no other module sets a gateway for a message.'),
      '#options' => $gateways,
      '#default_value' => $sms_settings->get('fallback_gateway'),
      '#empty_option' => $this->t('- No Fallback Gateway -'),
    ];

    $form['pages']['#tree'] = TRUE;
    $form['pages']['verify'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone verification path'),
      '#default_value' => $sms_settings->get('page.verify'),
      '#description' => $this->t('Path of the phone number verification form.'),
      '#size' => 30,
      '#required' => TRUE,
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $verify = $form_state->getValue(['pages', 'verify']);
    if (substr($verify, 0, 1) !== '/') {
      $form_state->setError($form['pages']['verify'], $this->t("Path must begin with a '/' character."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sms_settings = $this->config('sms.settings');
    $path_verify = $form_state->getValue(['pages', 'verify']);

    // Only rebuild routes if the path was changed.
    if ($path_verify != $sms_settings->get('page.verify')) {
      $this->routeBuilder->setRebuildNeeded();
    }

    $this->config('sms.settings')
      ->set('fallback_gateway', $form_state->getValue('fallback_gateway'))
      ->set('page.verify', $path_verify)
      ->save();

    $this->messenger()->addMessage($this->t('SMS settings saved.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sms.settings'];
  }

}

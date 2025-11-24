<?php

declare(strict_types=1);

namespace Drupal\acquia_connector\Controller;

use Drupal\acquia_connector\AuthService;
use Drupal\acquia_connector\Form\ApiKeyCredentialForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Authorization controller for Acquia Cloud.
 */
final class AuthController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * The auth service.
   *
   * @var \Drupal\acquia_connector\AuthService
   */
  private AuthService $authService;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private MessengerInterface $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Module List for getting the module's path.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private ModuleExtensionList $moduleList;

  /**
   * Form builder to return API credentials form.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  private FormBuilder $formBuilder;

  /**
   * Construct a new AuthController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\acquia_connector\AuthService $auth_service
   *   The auth service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   Module Extension List.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder service.
   */
  public function __construct(RendererInterface $renderer, RequestStack $request_stack, AuthService $auth_service, MessengerInterface $messenger, LoggerInterface $logger, ModuleExtensionList $module_list, FormBuilder $form_builder) {
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
    $this->authService = $auth_service;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->moduleList = $module_list;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new self(
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('acquia_connector.auth_service'),
      $container->get('messenger'),
      $container->get('acquia_connector.logger_channel'),
      $container->get('extension.list.module'),
      $container->get('form_builder')
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * The setup landing page.
   *
   * @return array
   *   The build array.
   */
  public function setup(): array {
    // Redirect to API Creds form.
    return $this->formBuilder->getForm(ApiKeyCredentialForm::class);
  }

  /**
   * Begins the API authorization process.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The redirect response.
   *
   * @deprecated in acquia_connector:4.1.1 and is removed from
   * acquia_connector:5.0.0. This is an internal route, do not extend!
   *
   * @internal
   */
  public function begin(): TrustedRedirectResponse {
    return new TrustedRedirectResponse(Url::fromRoute('acquia_connector.setup_oauth')->toString());
  }

  /**
   * Finalizes the OAuth authorization process when the user returns.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @deprecated in acquia_connector:4.1.1 and is removed from
   * acquia_connector:5.0.0. This is an internal route, do not extend!
   *
   * @internal
   *
   */
  public function return(): RedirectResponse {
    $this->messenger->addError($this->t('We could not retrieve account data, please re-authorize with your Acquia Cloud account. For more information check <a target="_blank" href=":url">this link</a>.', [
      ':url' => Url::fromUri('https://docs.acquia.com/cloud-platform/known-issues/#unable-to-log-in-through-acquia-connector')->getUri(),
    ]));
    return new TrustedRedirectResponse(Url::fromRoute('acquia_connector.setup_oauth')->toString());
  }

}

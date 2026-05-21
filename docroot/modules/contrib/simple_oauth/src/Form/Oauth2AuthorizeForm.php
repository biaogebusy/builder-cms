<?php

namespace Drupal\simple_oauth\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Authorize form.
 */
class Oauth2AuthorizeForm extends FormBase {

  /**
   * The httpFoundation factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected HttpFoundationFactoryInterface $httpFoundationFactory;

  /**
   * The known client repository service.
   *
   * @var \Drupal\simple_oauth\KnownClientsRepositoryInterface
   */
  protected KnownClientsRepositoryInterface $knownClientRepository;

  /**
   * Oauth2AuthorizeForm constructor.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface $http_foundation_factory
   *   The httpFoundation factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   */
  public function __construct(HttpFoundationFactoryInterface $http_foundation_factory, KnownClientsRepositoryInterface $known_clients_repository) {
    $this->httpFoundationFactory = $http_foundation_factory;
    $this->knownClientRepository = $known_clients_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_foundation_factory'),
      $container->get('simple_oauth.known_clients')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'simple_oauth_authorize_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \League\OAuth2\Server\AuthorizationServer|null $server
   *   The authorization server.
   * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequest|null $auth_request
   *   The authorization request.
   *
   * @return array
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AuthorizationServer $server = NULL, ?AuthorizationRequest $auth_request = NULL) {
    $client = $auth_request->getClient()->getDrupalEntity();
    $remember_approval = $client->get('remember_approval')->value;
    // Store the data temporarily.
    $form_state->set('server', $server);
    $form_state->set('remember_approval', $remember_approval);
    $form_state->set('auth_request', $auth_request);

    $form = [
      '#type' => 'container',
    ];

    $cacheability_metadata = new CacheableMetadata();

    $form['scopes'] = [
      '#title' => $this->t("You are allowing '%client' to:", ['%client' => $client->label()]),
      '#theme' => 'item_list',
      '#items' => [],
    ];

    $grant_type = 'authorization_code';

    /** @var \Drupal\simple_oauth\Entities\ScopeEntityInterface $scope */
    foreach ($auth_request->getScopes() as $scope) {
      $cacheability_metadata->addCacheableDependency($scope);
      $form['scopes']['#items'][$scope->getIdentifier()] = $scope->getDescription($grant_type);
    }

    $cacheability_metadata->applyTo($form['scopes']);

    $form['redirect_uri'] = [
      '#type' => 'hidden',
      '#value' => $auth_request->getRedirectUri(),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Allow'),
        '#button_type' => 'primary',
        '#authorized' => TRUE,
      ],
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Deny'),
        '#authorized' => FALSE,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $authorized = $form_state->getTriggeringElement()['#authorized'];
    $permission = 'grant simple_oauth codes';
    if ($authorized && !$this->currentUser()->hasPermission($permission)) {
      $form_state->setErrorByName('submit', $this->t("The '%permission' permission is required.", ['%permission' => $permission]));
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \League\OAuth2\Server\RequestTypes\AuthorizationRequest $auth_request */
    $auth_request = $form_state->get('auth_request');
    /** @var \League\OAuth2\Server\AuthorizationServer $server */
    $server = $form_state->get('server');
    $authorized = $form_state->getTriggeringElement()['#authorized'];
    $remember_approval = $form_state->get('remember_approval');

    // Once the user has approved or denied the client update the status
    // (true = approved, false = denied).
    $auth_request->setAuthorizationApproved($authorized);

    if ($authorized && $remember_approval) {
      $scopes = array_map(function (ScopeEntityInterface $scope) {
        return $scope->getIdentifier();
      }, $auth_request->getScopes());

      $this->knownClientRepository->rememberClient(
        $this->currentUser()->id(),
        $auth_request->getClient()->getIdentifier(),
        $scopes
      );
    }

    $response = $server->completeAuthorizationRequest($auth_request, new Response());
    $form_state->setResponse($this->httpFoundationFactory->createResponse($response));
  }

}

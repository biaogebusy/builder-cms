<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Authorize form.
 */
class Oauth2AuthorizeForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $messageFactory;

  /**
   * The foundation factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected $foundationFactory;

  /**
   * The authorization server.
   *
   * @var \League\OAuth2\Server\AuthorizationServer
   */
  protected $server;

  /**
   * The grant plugin manager.
   *
   * @var \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface
   */
  protected $grantManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The known client repository service.
   *
   * @var \Drupal\simple_oauth\KnownClientsRepositoryInterface
   */
  protected $knownClientRepository;

  /**
   * @var \League\OAuth2\Server\Repositories\ClientRepositoryInterface
   */
  protected $clientRepository;


  /**
   * Oauth2AuthorizeForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $message_factory
   *   The message factory.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface $foundation_factory
   *   The foundation factory.
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grant_manager
   *   The grant manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   * @param \League\OAuth2\Server\Repositories\ClientRepositoryInterface $client_repository
   *   The client repository service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, HttpMessageFactoryInterface $message_factory, HttpFoundationFactoryInterface $foundation_factory, Oauth2GrantManagerInterface $grant_manager, ConfigFactoryInterface $config_factory, KnownClientsRepositoryInterface $known_clients_repository, ClientRepositoryInterface $client_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messageFactory = $message_factory;
    $this->foundationFactory = $foundation_factory;
    $this->grantManager = $grant_manager;
    $this->configFactory = $config_factory;
    $this->knownClientRepository = $known_clients_repository;
    $this->clientRepository = $client_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('psr7.http_message_factory'),
      $container->get('psr7.http_foundation_factory'),
      $container->get('plugin.manager.oauth2_grant.processor'),
      $container->get('config.factory'),
      $container->get('simple_oauth.known_clients'),
      $container->get('simple_oauth.repositories.client')
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
   *
   * @return array
   *   The form structure.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    if ($request->get('response_type') == 'code') {
      $grant_type = 'code';
    }
    elseif ($request->get('response_type') == 'token') {
      $grant_type = 'implicit';
    }
    else {
      $grant_type = NULL;
    }
    $client_id = $request->get('client_id');
    $client_drupal_entity = $this->clientRepository
      ->getClientEntity($client_id);
    if (empty($client_drupal_entity)) {
      $server_request = $this->messageFactory->createRequest($request);
      throw OAuthServerException::invalidClient($server_request);
    }
    $consumer_entity = $client_drupal_entity->getDrupalEntity();
    $this->server = $this
      ->grantManager
      ->getAuthorizationServer($grant_type, $consumer_entity);

    // Transform the HTTP foundation request object into a PSR-7 object. The
    // OAuth library expects a PSR-7 request.
    $psr7_request = $this->messageFactory->createRequest($request);
    // Validate the HTTP request and return an AuthorizationRequest object.
    // The auth request object can be serialized into a user's session.
    $auth_request = $this->server->validateAuthorizationRequest($psr7_request);

    // Store the auth request temporarily.
    $form_state->set('auth_request', $auth_request);

    $manager = $this->entityTypeManager;
    $form = [
      '#type' => 'container',
    ];

    $cacheablity_metadata = new CacheableMetadata();

    $form['client'] = $manager->getViewBuilder('consumer')->view($consumer_entity);
    $form['scopes'] = [
      '#title' => $this->t('Permissions'),
      '#theme' => 'item_list',
      '#items' => [],
    ];

    $client_roles = [];
    foreach ($consumer_entity->get('roles') as $role_item) {
      $client_roles[$role_item->target_id] = $role_item->entity;
    }

    /** @var \Drupal\simple_oauth\Entities\ScopeEntityNameInterface $scope */
    foreach ($auth_request->getScopes() as $scope) {
      $cacheablity_metadata->addCacheableDependency($scope);
      $form['scopes']['#items'][] = $scope->getName();

      unset($client_roles[$scope->getIdentifier()]);
    }

    // Add the client roles that were not explicitly requested to the list.
    foreach ($client_roles as $client_role) {
      $cacheablity_metadata->addCacheableDependency($client_role);
      $form['scopes']['#items'][] = $client_role->label();
    }

    $cacheablity_metadata->applyTo($form['scopes']);

    $form['redirect_uri'] = [
      '#type' => 'hidden',
      '#value' => $request->get('redirect_uri') ?
      $request->get('redirect_uri') :
      $consumer_entity->get('redirect')->value,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Grant'),
    ];

    return $form;
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
    if ($auth_request = $form_state->get('auth_request')) {
      $can_grant_codes = $this->currentUser()
        ->hasPermission('grant simple_oauth codes');
      $redirect_response = Oauth2AuthorizeController::redirectToCallback(
        $auth_request,
        $this->server,
        $this->currentUser(),
        (bool) $form_state->getValue('submit') && $can_grant_codes,
        (bool) $this->configFactory->get('simple_oauth.settings')->get('remember_clients'),
        $this->knownClientRepository
      );
      $form_state->setResponse($redirect_response);
    }
  }

}

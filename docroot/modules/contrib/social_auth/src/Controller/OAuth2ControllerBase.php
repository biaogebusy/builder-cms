<?php

namespace Drupal\social_auth\Controller;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\AuthManager\OAuth2ManagerInterface;
use Drupal\social_auth\Event\LoginEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\Plugin\Network\NetworkInterface;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\User\SocialAuthUserInterface;
use Drupal\social_auth\User\UserAuthenticator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Handle responses for Social Auth implementer controllers.
 */
class OAuth2ControllerBase extends ControllerBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  protected NetworkManager $networkManager;

  /**
   * The Social Auth user authenticator..
   *
   * @var \Drupal\social_auth\User\UserAuthenticator
   */
  protected UserAuthenticator $userAuthenticator;

  /**
   * The provider authentication manager.
   *
   * @var \Drupal\social_auth\AuthManager\OAuth2ManagerInterface|null
   */
  protected ?OAuth2ManagerInterface $providerManager = NULL;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $request;

  /**
   * The Social Auth data handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  protected SocialAuthDataHandler $dataHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $dispatcher;

  /**
   * The implement plugin id.
   *
   * @var string|null
   */
  protected ?string $pluginId = NULL;

  /**
   * The module name.
   *
   * @var string|null
   */
  protected ?string $module = NULL;

  /**
   * Error code produced in the processCallback method.
   *
   * @var string|null
   */
  private ?string $processCallbackError = NULL;

  /**
   * OAuth2ControllerBase constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Network manager.
   * @param \Drupal\social_auth\User\UserAuthenticator $user_authenticator
   *   User authenticator.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Request stack.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   Social Auth data handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger,
    MessengerInterface $messenger,
    NetworkManager $network_manager,
    UserAuthenticator $user_authenticator,
    RequestStack $request,
    SocialAuthDataHandler $data_handler,
    RendererInterface $renderer,
    EventDispatcherInterface $dispatcher,
  ) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger;
    $this->messenger = $messenger;
    $this->networkManager = $network_manager;
    $this->userAuthenticator = $user_authenticator;
    $this->request = $request;
    $this->dataHandler = $data_handler;
    $this->renderer = $renderer;
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_authenticator'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler'),
      $container->get('renderer'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Sets up the class for the provided network.
   *
   * @param \Drupal\social_auth\Plugin\Network\NetworkInterface $network
   *   Network.
   */
  private function setUp(NetworkInterface $network): void {
    $this->pluginId = $network->getPluginId();
    $this->module = $network->getPluginDefinition()['social_network'];
    $auth_manager_class = $network->getPluginDefinition()['auth_manager'];
    $this->providerManager = new $auth_manager_class(
      $this->configFactory,
      $this->loggerFactory,
      $this->request
    );

    // Sets the plugin id in user authenticator.
    $this->userAuthenticator->setPluginId($network->getPluginId());

    // Sets the session prefix.
    $this->dataHandler->setSessionPrefix($network->getPluginId());

    // Sets the session keys to nullify if user could not be logged in.
    $this->userAuthenticator->setSessionKeysToNullify([
      'access_token',
      'oauth2state',
    ]);
  }

  /**
   * Callback response router handler for networks.
   */
  public function callback(NetworkInterface $network): RedirectResponse {
    $this->setUp($network);
    $social_auth_user = $this->processCallback();
    if ($social_auth_user !== NULL) {
      $redirect = $this->userAuthenticator->authenticateUser($social_auth_user);
      // Only trigger the event if Drupal fully authenticated the user.
      if ($this->currentUser()->isAuthenticated()) {
        $event = new LoginEvent($this->currentUser(), $social_auth_user, $this->pluginId);
        $this->dispatcher->dispatch($event, SocialAuthEvents::USER_LOGIN);
      }
      return $redirect;
    }
    else {
      $callbackError = $this->getProcessCallbackError();
      if (!is_null($callbackError)) {
        $this->messenger->addError($callbackError);
        // Redirecting to user.login would cause infinite loop.
        return $this->redirect('<front>');
      }
    }

    return $this->redirect('user.login');
  }

  /**
   * Response for implementer authentication url.
   *
   * Redirects the user to provider for authentication.
   *
   * This is done in a render context in order to bubble cacheable metadata
   * created during authentication URL generation.
   *
   * @see https://www.drupal.org/project/social_auth/issues/3033444
   */
  public function redirectToProvider(NetworkInterface $network): Response {
    $this->setUp($network);
    $context = new RenderContext();

    /** @var \Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse $response */
    $response = $this->renderer->executeInRenderContext($context, function () {
      try {
        /** @var \League\OAuth2\Client\Provider\AbstractProvider|false $client */
        $client = $this->networkManager->createInstance($this->pluginId)->getSdk();

        // If provider client could not be obtained.
        if (!$client) {
           $this->messenger->addError($this->t('%module not configured properly. Contact site administrator.', ['%module' => $this->module]));
           return $this->redirect('user.login');
        }

        /*
         * If destination parameter is set, save it.
         *
         * The destination parameter is also _removed_ from the current request
         * to prevent it from overriding Social Auth's TrustedRedirectResponse.
         *
         * @see https://www.drupal.org/project/drupal/issues/2950883
         *
         * TODO: Remove the remove() call after 2950883 is solved.
         */
        $destination = $this->request->getCurrentRequest()->get('destination');
        if ($destination) {
          $this->userAuthenticator->setDestination($destination);
          $this->request->getCurrentRequest()->query->remove('destination');
        }

        // Provider service was returned, inject it to $providerManager.
        $this->providerManager->setClient($client);

        // Generates the URL for authentication.
        $auth_url = $this->providerManager->getAuthorizationUrl();

        $state = $this->providerManager->getState();
        $this->dataHandler->set('oauth2state', $state);

        $this->userAuthenticator->dispatchBeforeRedirect($destination);
        return new TrustedRedirectResponse($auth_url);
      }
      catch (PluginException) {
        $this->messenger->addError($this->t('There has been an error when creating plugin.'));
        return $this->redirect('user.login');
      }
    });

    // Add bubbleable metadata to the response.
    if ($response instanceof TrustedRedirectResponse && !$context->isEmpty()) {
      $bubbleable_metadata = $context->pop();
      $response->addCacheableDependency($bubbleable_metadata);
    }

    return $response;
  }

  /**
   * Gets the error details for the processCallbackError property.
   *
   * @return string|null
   *   Error detail or null otherwise.
   */
  private function getProcessCallbackError(): ?string {
    $errors = [
      'config' => $this->t('%module not configured properly. Contact site administrator.', ['%module' => $this->module]),
      'oauth' => $this->t('Login failed. Invalid OAuth2 state.'),
      'token' => $this->t('Authentication failed. Contact site administrator.'),
      'user_info' => $this->t('Login failed, could not load user profile. Contact site administrator.'),
      'exception' => $this->t('There has been an error when creating plugin.'),
      'unknown' => $this->t('Unknown error.'),
    ];

    return is_null($this->processCallbackError) ?
      NULL :
      ($errors[$this->processCallbackError] ?? $errors['unknown']);
  }

  /**
   * Sets the error code for the processCallbackError property.
   *
   * @param string $errorCode
   *   Error code to set.
   */
  private function setProcessCallbackError(string $errorCode) {
    $this->processCallbackError = $errorCode;
  }

  /**
   * Resets the error code for the processCallbackError property.
   */
  private function resetProcessCallbackError() {
    $this->processCallbackError = NULL;
  }

  /**
   * Process implementer callback path.
   *
   * @return \Drupal\social_auth\User\SocialAuthUserInterface|null
   *   The user info if successful. Null otherwise.
   */
  private function processCallback(): ?SocialAuthUserInterface {
    // Clean up any possible previous value first.
    $this->resetProcessCallbackError();

    try {
      $client = $this->networkManager->createInstance($this->pluginId)->getSdk();

      // If provider client could not be obtained.
      if (!$client) {
        $this->setProcessCallbackError('config');
        return NULL;
      }

      $state = $this->dataHandler->get('oauth2state');
      $retrievedState = $this->request->getCurrentRequest()->query->get('state');
      if (empty($retrievedState) || ($retrievedState !== $state)) {
        $this->userAuthenticator->nullifySessionKeys();
        $this->setProcessCallbackError('oauth');
        return NULL;
      }

      $this->providerManager->setClient($client)->authenticate();

      $access_token = $this->providerManager->getAccessToken();
      if (empty($access_token)) {
        $this->setProcessCallbackError('token');
        return NULL;
      }
      // Saves access token to session.
      $this->dataHandler->set('access_token', $access_token);

      // Gets user's info from provider.
      if (!$profile = $this->providerManager->getUserInfo()) {
        $this->setProcessCallbackError('user_info');
        return NULL;
      }

      return $profile;
    }
    catch (PluginException) {
      $this->setProcessCallbackError('exception');
      return NULL;
    }
  }

  /**
   * Checks if there was an error during authentication with provider.
   *
   * When there is an authentication problem in a provider (e.g. user did not
   * authorize the app), a query to the client containing an error key is often
   * returned. This method checks for such key, dispatches an event, and returns
   * a redirect object where there is an authentication error.
   *
   * @param string $key
   *   The query parameter key to check for authentication error.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   Redirect response object that may be returned by the controller or null.
   */
  protected function checkAuthError(string $key = 'error'): ?RedirectResponse {
    $request_query = $this->request->getCurrentRequest()->query;

    // Checks if authentication failed.
    if ($request_query->has($key)) {
      $this->messenger->addError($this->t('You could not be authenticated.'));

      $response = $this->userAuthenticator->dispatchAuthenticationError($request_query->get($key));
      if ($response) {
        return $response;
      }

      return $this->redirect('user.login');
    }

    return NULL;
  }

}
